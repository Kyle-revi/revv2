<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CloudflareAI
{
    public function run(string $model, array $payload): array
    {
        $accountId = trim((string) config('services.cloudflare.account_id'), "\" \t\n\r\0\x0B'");
        $token = trim((string) config('services.cloudflare.token'), "\" \t\n\r\0\x0B'");
        $gateway = trim((string) config('services.cloudflare.gateway'), "\" \t\n\r\0\x0B'");

        if (empty($accountId) || empty($token)) {
            throw new RuntimeException(
                'Cloudflare Workers AI credentials are missing. '.
                'Account ID: '.($accountId ?: 'empty').' | '.
                'Token: '.($token ? 'present' : 'empty').'. '.
                'Check config/services.php and .env file.'
            );
        }

        $baseUrl = $gateway
    ? "https://gateway.ai.cloudflare.com/v1/{$accountId}/{$gateway}"
    : "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai";

        $url = "{$baseUrl}/run/{$model}";

        // FIX #2: Explicit HTTP 30-second timeout
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ])
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(1, 500)
            ->post($url, $payload);

        if ($response->failed()) {
            $errorMsg = $response->json('errors.0.message', $response->body() ?: 'Unknown error');
            Log::error('Cloudflare Workers AI request failed', [
                'status' => $response->status(),
                'url' => $url,
                'error' => $errorMsg,
                'payload' => $payload,
            ]);

            throw new RuntimeException("Cloudflare API returned HTTP {$response->status()}: {$errorMsg}");
        }

        $result = $response->json('result');

        if (! is_array($result) || ! isset($result['response'])) {
            throw new RuntimeException('Invalid response format from Cloudflare Workers AI');
        }

        $usage = $response->json('result.usage') ?? $response->json('usage');
        if (! is_array($usage)) {
            $promptHeader = (int) $response->header('cf-aig-prompt-tokens');
            $completionHeader = (int) $response->header('cf-aig-completion-tokens');
            if ($promptHeader > 0 || $completionHeader > 0) {
                $usage = [
                    'prompt_tokens' => $promptHeader,
                    'completion_tokens' => $completionHeader,
                    'total_tokens' => $promptHeader + $completionHeader,
                ];
            } else {
                $promptText = json_encode($payload['messages'] ?? []);
                $responseText = is_string($result['response']) ? $result['response'] : json_encode($result['response']);
                $promptTokens = (int) ceil(mb_strlen($promptText) / 3.8);
                $completionTokens = (int) ceil(mb_strlen($responseText) / 3.8);
                $usage = [
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $promptTokens + $completionTokens,
                ];
            }
        }
        $result['usage'] = $usage;

        return $result;
    }

    /**
     * Run multiple model payloads concurrently using Http::pool().
     *
     * @param  array<int|string, array{model: string, payload: array}>  $tasks
     * @return array<int|string, array>
     */
    public function runPool(array $tasks): array
    {
        if (empty($tasks)) {
            return [];
        }

        $accountId = trim((string) config('services.cloudflare.account_id'), "\" \t\n\r\0\x0B'");
        $token = trim((string) config('services.cloudflare.token'), "\" \t\n\r\0\x0B'");
        $gateway = trim((string) config('services.cloudflare.gateway'), "\" \t\n\r\0\x0B'");

        if (empty($accountId) || empty($token)) {
            throw new RuntimeException('Cloudflare Workers AI credentials are missing.');
        }

        $baseUrl = $gateway
            ? "https://gateway.ai.cloudflare.com/v1/{$accountId}/{$gateway}"
            : "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai";

        $responses = Http::pool(function ($pool) use ($tasks, $baseUrl, $token) {
            $requests = [];
            foreach ($tasks as $key => $task) {
                $model = $task['model'];
                $payload = $task['payload'];
                $url = "{$baseUrl}/run/{$model}";

                $requests[(string) $key] = $pool->as((string) $key)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$token,
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout(35)
                    ->connectTimeout(10)
                    ->retry(1, 500)
                    ->post($url, $payload);
            }

            return $requests;
        });

        $results = [];
        foreach ($tasks as $key => $task) {
            $strKey = (string) $key;
            $response = $responses[$strKey] ?? null;

            if ($response instanceof Response && $response->successful()) {
                $result = $response->json('result');
                if (is_array($result) && isset($result['response'])) {
                    $results[$key] = $result;

                    continue;
                }
            }

            Log::warning("Cloudflare AI pool task [{$key}] failed", [
                'status' => $response instanceof Response ? $response->status() : 'exception',
                'error' => $response instanceof Response ? $response->body() : 'no response',
            ]);
            $results[$key] = ['response' => []];
        }

        return $results;
    }

    public function generateSummary(array $stats, array $options = []): string
    {
        $weakSummary = collect($stats['weakTopics'] ?? [])
            ->map(fn ($t) => is_array($t)
                ? ($t['question'] ?? $t['topic'] ?? '').' ('.($t['pct_correct'] ?? 0).'% correct)'
                : (string) $t
            )
            ->filter()
            ->implode(', ');

        $settingsResolver = app(AiSettingsResolver::class);
        $systemPrompt = (string) ($options['system_prompt'] ?? $settingsResolver->getPromptTemplate('class_summary', 'system'));
        $userTemplate = (string) ($options['user_template'] ?? $settingsResolver->getPromptTemplate('class_summary', 'user_template'));

        $userPrompt = $settingsResolver->renderTemplate($userTemplate, [
            'class_average' => $stats['classAverage'] ?? 0,
            'pass_count' => $stats['passCount'] ?? 0,
            'fail_count' => $stats['failCount'] ?? 0,
            'weak_summary' => $weakSummary,
        ]);

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ];

        $result = $this->run($settingsResolver->getModel(), [
            'messages' => $messages,
            'max_tokens' => $settingsResolver->getMaxTokens(),
        ]);

        return $result['response'] ?? 'No AI summary available.';
    }

    public function generateStudentAssessmentAnalysis(array $stats, array $options = []): string
    {
        $weakSummary = collect($stats['weakTopics'] ?? [])
            ->filter(fn ($t) => ($t['pct_correct'] ?? 100) < 100)
            ->map(fn ($t) => is_array($t)
                ? ($t['question'] ?? $t['topic'] ?? '').' ('.($t['pct_correct'] ?? 0).'% correct)'
                : (string) $t
            )
            ->filter()
            ->implode("\n- ");

        if (empty($weakSummary)) {
            $weakSummary = 'No weak questions detected. Student scored well across all questions.';
        } else {
            $weakSummary = '- '.$weakSummary;
        }

        $settingsResolver = app(AiSettingsResolver::class);
        $systemPrompt = (string) ($options['system_prompt'] ?? "You are an educational tutor analyzing an individual student's formal assessment performance. Reply in this exact clean format with clear line breaks:\n\nStudent Performance: [1-2 sentences evaluating student score vs class average]\nAssessment Status: [Passed / Needs Improvement / Failed]\nWeak Areas:\n[bullet list of questions or topics the student struggled with]\nRecommendation: [1-2 actionable study recommendations for the student]");

        $userPrompt = "Student: {$stats['student']}\nClass: {$stats['class']}\nStudent Average: {$stats['averageScore']}%\nClass Average: {$stats['classAverage']}%\nPassed Attempts: {$stats['passedCount']}/{$stats['totalAttempts']}\n\nQuestions Needing Review:\n{$weakSummary}";

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ];

        $result = $this->run($settingsResolver->getModel(), [
            'messages' => $messages,
            'max_tokens' => $settingsResolver->getMaxTokens(),
        ]);

        return $result['response'] ?? 'No AI analysis available.';
    }
}
