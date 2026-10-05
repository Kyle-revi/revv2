<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\ClassModel;
use Illuminate\Support\Facades\Cache;

class AiSettingsResolver
{
    private const GLOBAL_CACHE_KEY = 'ai_settings.global';

    public const AVAILABLE_MODELS = [
        '@cf/meta/llama-3.2-3b-instruct' => 'Llama 3.2 3B (fast)',
        '@cf/meta/llama-3.1-8b-instruct' => 'Llama 3.1 8B (balanced)',
        '@cf/meta/llama-3.3-70b-instruct-fp8-fast' => 'Llama 3.3 70B (powerful)',
    ];

    private const GLOBAL_DEFAULTS = [
        'feature.quiz_generation_enabled' => true,
        'feature.quiz_insights_enabled' => true,
        'feature.class_summary_enabled' => true,
        'feature.assessment_analysis_enabled' => true,
        'model.default' => '@cf/meta/llama-3.2-3b-instruct',
        'model.max_tokens' => 600,
        'prompt.quiz_generation.system' => 'You are an expert academic examiner and board examination test developer. Output ONLY a JSON array of exactly {num_questions} question objects. Questions must be strictly lecture-based, concept-focused, and non-duplicative. Never test or copy illustrative examples or specific numbers from worked examples. No markdown, no backticks, no explanation. Start with [ and end with ].',
        'prompt.quiz_generation.user_template' => "Generate EXACTLY {num_questions} high-quality, lecture-based multiple-choice questions. NOT more. NOT less. EXACTLY {num_questions}.\nDifficulty: {difficulty}.\nModule: {module_title}\nDescription: {module_description}\n\nContent:\n{combined_text}\n\nStrict Rules:\n- Return ONLY a valid JSON array.\n- The array must have EXACTLY {num_questions} objects.\n- Each object: {\"question\":\"...\",\"options\":{\"A\":\"...\",\"B\":\"...\",\"C\":\"...\",\"D\":\"...\"},\"correct\":\"A|B|C|D\"}\n- STRICTLY LECTURE-BASED: Base questions solely on core principles, definitions, classifications, standards, and rules taught in the lecture.\n- NO WORKED EXAMPLES: Do NOT use, copy, or convert worked examples, case-study scenarios, numerical illustrations, or specific company/person names from the text into questions.\n- NO PRE-EXISTING QUESTIONS: If the text contains practice drills, sample quizzes, exercises, or answer keys, IGNORE them and test the underlying concepts instead.\n- No markdown, no backticks, no extra text.\n- Start with [ and end with ]\n- Stop after {num_questions} questions.",
        'prompt.quiz_insights.system' => 'You are an expert academic mentor and board examination reviewer. Provide concept-level diagnostic feedback on the student\'s quiz attempt. Group findings into underlying subject topics rather than reciting question numbers or option letters. Never write "The student identified Option A" or "The student incorrectly identified". Be concise, analytical, and professional.',
        'prompt.quiz_insights.user_template' => "Student scored {score}% on '{module_title}'.\n\nStudent Assessment Breakdown:\n{answers_context}\n\nAnalyze conceptual mastery and reply in this exact format with clear headings:\n\nStrong Areas:\n- **[Concept/Topic Name]:** [1 concise sentence on the principle or rule mastered]\n- **[Second Concept]:** [1 concise sentence on demonstrated strength]\n\nWeak Areas:\n- **[Missed Topic/Rule]:** [1 concise sentence diagnosing the misconception or distinction missed without citing option letters]\n- **[Second Missed Topic]:** [1 concise sentence on the specific principle needing review]\n\nRecommendation:\n1. [Specific topic or standard to re-read in lecture notes]\n2. [Targeted distinction or formula to clarify to prevent repeating the mistake]\n3. [Actionable practice step before retaking]",
        'prompt.class_summary.system' => 'You are an educational performance analyst. Reply in this exact format with line breaks between each section:\n\nClass Average: [value]\nPass/Fail Status: [value]\nWeak Areas:\n- [area 1]\n- [area 2]\nRecommendation: [one sentence]',
        'prompt.class_summary.user_template' => 'Class average: {class_average}%. Pass count: {pass_count}, Fail count: {fail_count}. Weak areas: {weak_summary}.',
    ];

    public function isFeatureEnabled(string $feature, ?ClassModel $class = null): bool
    {
        $globalKey = "feature.{$feature}_enabled";
        $globalEnabled = (bool) $this->getGlobal($globalKey, true);

        if (! $globalEnabled) {
            return false;
        }

        if ($class === null) {
            return true;
        }

        $classSettings = $this->getClassSettings($class);

        return (bool) data_get($classSettings, "features.{$feature}_enabled", true);
    }

    public function getPromptTemplate(string $feature, string $type): string
    {
        return (string) $this->getGlobal("prompt.{$feature}.{$type}", '');
    }

    public function getModel(): string
    {
        return (string) $this->getGlobal('model.default', '@cf/meta/llama-3.2-3b-instruct');
    }

    public function getMaxTokens(): int
    {
        return (int) $this->getGlobal('model.max_tokens', 400);
    }

    public function getClassQuizDefaults(ClassModel $class): array
    {
        $settings = $this->getClassSettings($class);

        return [
            'question_count' => (int) data_get($settings, 'quiz_defaults.question_count', 10),
            'difficulty' => (string) data_get($settings, 'quiz_defaults.difficulty', 'Normal'),
        ];
    }

    public function updateGlobalSettings(array $validated): void
    {
        $allowed = [
            'feature.quiz_generation_enabled',
            'feature.quiz_insights_enabled',
            'feature.class_summary_enabled',
            'feature.assessment_analysis_enabled',
            'model.default',
            'model.max_tokens',
            'prompt.quiz_generation.system',
            'prompt.quiz_generation.user_template',
            'prompt.quiz_insights.system',
            'prompt.quiz_insights.user_template',
            'prompt.class_summary.system',
            'prompt.class_summary.user_template',
        ];

        foreach ($allowed as $key) {
            $value = data_get($validated, $key);
            if ($value === null) {
                continue;
            }

            AiSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]
            );
        }

        $this->clearGlobalCache();
    }

    public function updateClassSettings(ClassModel $class, array $validated): void
    {
        $current = $this->getClassSettings($class);

        $next = [
            'features' => [
                'quiz_generation_enabled' => (bool) data_get($validated, 'features.quiz_generation_enabled', data_get($current, 'features.quiz_generation_enabled', true)),
                'quiz_insights_enabled' => (bool) data_get($validated, 'features.quiz_insights_enabled', data_get($current, 'features.quiz_insights_enabled', true)),
                'class_summary_enabled' => (bool) data_get($validated, 'features.class_summary_enabled', data_get($current, 'features.class_summary_enabled', true)),
                'assessment_analysis_enabled' => (bool) data_get($validated, 'features.assessment_analysis_enabled', data_get($current, 'features.assessment_analysis_enabled', true)),
            ],
            'quiz_defaults' => [
                'question_count' => (int) data_get($validated, 'quiz_defaults.question_count', data_get($current, 'quiz_defaults.question_count', 10)),
                'difficulty' => (string) data_get($validated, 'quiz_defaults.difficulty', data_get($current, 'quiz_defaults.difficulty', 'Normal')),
            ],
        ];

        $class->ai_settings = $next;
        $class->save();
    }

    public function renderTemplate(string $template, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    public function getGlobalSnapshot(): array
    {
        return array_merge(self::GLOBAL_DEFAULTS, $this->getGlobalSettingsMap());
    }

    public function getClassSettings(ClassModel $class): array
    {
        $stored = is_array($class->ai_settings) ? $class->ai_settings : [];

        return [
            'features' => [
                'quiz_generation_enabled' => (bool) data_get($stored, 'features.quiz_generation_enabled', true),
                'quiz_insights_enabled' => (bool) data_get($stored, 'features.quiz_insights_enabled', true),
                'class_summary_enabled' => (bool) data_get($stored, 'features.class_summary_enabled', true),
                'assessment_analysis_enabled' => (bool) data_get($stored, 'features.assessment_analysis_enabled', true),
            ],
            'quiz_defaults' => [
                'question_count' => (int) data_get($stored, 'quiz_defaults.question_count', 10),
                'difficulty' => (string) data_get($stored, 'quiz_defaults.difficulty', 'Normal'),
            ],
        ];
    }

    private function getGlobal(string $key, mixed $default): mixed
    {
        $settings = $this->getGlobalSnapshot();

        return $settings[$key] ?? $default;
    }

    private function getGlobalSettingsMap(): array
    {
        return Cache::rememberForever(self::GLOBAL_CACHE_KEY, function (): array {
            return AiSetting::query()
                ->get(['key', 'value'])
                ->mapWithKeys(function (AiSetting $setting): array {
                    $decoded = json_decode((string) $setting->value, true);

                    return [$setting->key => $decoded];
                })
                ->toArray();
        });
    }

    private function clearGlobalCache(): void
    {
        Cache::forget(self::GLOBAL_CACHE_KEY);
    }
}
