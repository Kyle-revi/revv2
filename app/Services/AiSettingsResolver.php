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
        'model.quiz_insights' => '@cf/meta/llama-3.1-8b-instruct',
        'model.max_tokens' => 600,
        'prompt.quiz_generation.system' => 'You are an expert academic examiner and board examination test developer. Output ONLY a JSON array of exactly {num_questions} question objects. Questions must be strictly lecture-based, concept-focused, and non-duplicative. Never test or copy illustrative examples or specific numbers from worked examples. No markdown, no backticks, no explanation. Start with [ and end with ].',
        'prompt.quiz_generation.user_template' => "Generate EXACTLY {num_questions} high-quality, lecture-based multiple-choice questions. NOT more. NOT less. EXACTLY {num_questions}.\nDifficulty: {difficulty}.\nModule: {module_title}\nDescription: {module_description}\n\nContent:\n{combined_text}\n\nStrict Rules:\n- Return ONLY a valid JSON array.\n- The array must have EXACTLY {num_questions} objects.\n- Each object: {\"question\":\"...\",\"options\":{\"A\":\"...\",\"B\":\"...\",\"C\":\"...\",\"D\":\"...\"},\"correct\":\"A|B|C|D\"}\n- STRICTLY LECTURE-BASED: Base questions solely on core principles, definitions, classifications, standards, and rules taught in the lecture.\n- NO WORKED EXAMPLES: Do NOT use, copy, or convert worked examples, case-study scenarios, numerical illustrations, or specific company/person names from the text into questions.\n- NO PRE-EXISTING QUESTIONS: If the text contains practice drills, sample quizzes, exercises, or answer keys, IGNORE them and test the underlying concepts instead.\n- No markdown, no backticks, no extra text.\n- Start with [ and end with ]\n- Stop after {num_questions} questions.",
        'prompt.quiz_insights.system' => "You are an expert academic mentor and board examination reviewer providing high-level conceptual diagnostic feedback directly to a college student after their quiz.\n\nSTRICT RULES:\n1. SECOND PERSON ONLY: Always address the student directly as \"You\" / \"Your\" (e.g., \"You demonstrated a clear understanding of...\", \"Focus on reviewing...\"). Write recommendations in active imperative verbs (e.g., \"1. Re-read the section on...\", \"2. Compare the distinctions between...\"). NEVER use third-person words (STRICTLY FORBIDDEN: \"the student\", \"the learner\", \"they\", \"them\", \"their\", \"he\", \"she\", \"his\", \"her\").\n2. ABSOLUTELY ZERO CITATIONS: NEVER include academic citations, author names, years, page numbers, DSM editions, or outside references (STRICTLY FORBIDDEN: \"(APA, 2020)\", \"(p. 145)\", \"(Wampold, 2001)\", or any author/year parentheses). Reference only lecture concepts and principles.\n3. CONCISE SYNTHESIS & STRICT BULLET LIMIT (MAX 3 BULLETS PER SECTION):\n   - Strong Areas: EXACTLY 1 to 2 bullet points.\n   - Weak Areas: EXACTLY 2 to 3 bullet points. Group all missed questions into the top 2-3 overarching themes. NEVER output more than 3 bullet points.\n   - Recommendation: EXACTLY 2 to 3 numbered action steps.\n   STRICTLY FORBIDDEN: Outputting more than 3 bullet points in any section. Do NOT analyze individual questions one-by-one.\n4. NEVER QUOTE QUESTIONS OR OPTIONS VERBATIM: Do NOT quote or reproduce test questions, answer options, or exam mechanics (STRICTLY FORBIDDEN: quoting \"to the question '...'\", \"where you selected '...'\", \"your correct answer to...\", \"in the question '...'\", or repeating choices word-for-word). Write exactly 1 to 2 short, punchy sentences per bullet synthesizing the underlying concept directly.\n5. FACTUAL TRUTH (HANDLE NEGATIVE QUESTIONS WISELY): Be alert to negative question stems (e.g. 'Which is NOT true', 'All EXCEPT'). In negative questions, the correct option on the test is a false statement. ALWAYS explain the true, accurate lecture principle to the student (e.g. psychological disorders are UNEXPECTED in cultural context; cultural norms VARY across cultures and are NOT universal; poverty INCREASES vulnerability). NEVER claim a false statement is the correct lecture rule.\n6. FOCUSED ACTION PLAN: Give 2 to 3 concrete study steps targeting ONLY the concepts the student missed. Never advise them to re-study concepts they already got right.",
        'prompt.quiz_insights.user_template' => "You scored {score}% on '{module_title}'.\n\nQuiz Performance Summary:\n{answers_context}\n\nSynthesize conceptual mastery directly into concise, punchy insights without quoting questions or answer choices verbatim. Follow this exact format (MAX 3 BULLETS PER SECTION, 1-2 SHORT SENTENCES PER BULLET):\n\nStrong Areas:\n- **[Mastered Theme]:** You demonstrated a solid grasp of [concept principle in 1-2 short sentences].\n- **[Second Theme]:** You showed good understanding of [second concept principle in 1-2 short sentences, or omit if only 1 theme].\n\nWeak Areas:\n- **[Primary Weakness Theme]:** [Directly explain the accurate lecture rule and resolve the misconception in 1-2 short sentences without repeating question text or options].\n- **[Second Weakness Theme]:** [Directly explain second distinction in 1-2 short sentences].\n\nRecommendation:\n1. [Direct actionable study step 1].\n2. [Direct actionable study step 2].\n3. [Direct actionable study step 3].",
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

    public function getInsightModel(): string
    {
        return (string) $this->getGlobal('model.quiz_insights', '@cf/meta/llama-3.1-8b-instruct');
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
            'model.quiz_insights',
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
