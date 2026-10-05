<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_settings')) {
            return;
        }

        $now = now();

        $updates = [
            'model.max_tokens' => 600,
            'prompt.quiz_generation.system' => 'You are an expert academic examiner and board examination test developer. Output ONLY a JSON array of exactly {num_questions} question objects. Questions must be strictly lecture-based, concept-focused, and non-duplicative. Never test or copy illustrative examples or specific numbers from worked examples. No markdown, no backticks, no explanation. Start with [ and end with ].',
            'prompt.quiz_generation.user_template' => "Generate EXACTLY {num_questions} high-quality, lecture-based multiple-choice questions. NOT more. NOT less. EXACTLY {num_questions}.\nDifficulty: {difficulty}.\nModule: {module_title}\nDescription: {module_description}\n\nContent:\n{combined_text}\n\nStrict Rules:\n- Return ONLY a valid JSON array.\n- The array must have EXACTLY {num_questions} objects.\n- Each object: {\"question\":\"...\",\"options\":{\"A\":\"...\",\"B\":\"...\",\"C\":\"...\",\"D\":\"...\"},\"correct\":\"A|B|C|D\"}\n- STRICTLY LECTURE-BASED: Base questions solely on core principles, definitions, classifications, standards, and rules taught in the lecture.\n- NO WORKED EXAMPLES: Do NOT use, copy, or convert worked examples, case-study scenarios, numerical illustrations, or specific company/person names from the text into questions.\n- NO PRE-EXISTING QUESTIONS: If the text contains practice drills, sample quizzes, exercises, or answer keys, IGNORE them and test the underlying concepts instead.\n- No markdown, no backticks, no extra text.\n- Start with [ and end with ]\n- Stop after {num_questions} questions.",
            'prompt.quiz_insights.system' => 'You are an expert academic mentor and board exam review advisor. Provide highly specific, diagnostic feedback based on the student\'s quiz performance. Reply in the exact format requested with clear section headers. Do NOT give generic advice like "study more" or "review notes". Cite specific concepts, rules, standards, or criteria.',
            'prompt.quiz_insights.user_template' => "Student scored {score}% on '{module_title}'.\n\nStudent Answers Context:\n{answers_context}\n\nAnalyze the performance and reply in this exact format with clear headings:\n\nStrong Areas:\n- [Specific concept, standard, or rule demonstrated with mastery, explaining what was applied correctly]\n- [Additional specific strong concept]\n\nWeak Areas:\n- [Specific concept, rule, or calculation method missed, diagnosing the specific confusion or misconception]\n- [Additional specific weak area identified from incorrect answers]\n\nRecommendation:\n1. [Specific action step naming the exact topic or rule to revisit and re-read]\n2. [Targeted distinction or practical rule to master to prevent repeating the mistake]\n3. [Actionable next study step before retaking]",
        ];

        foreach ($updates as $key => $value) {
            DB::table('ai_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]
            );
        }

        Cache::forget('ai_settings.global');
    }

    public function down(): void
    {
        // Preserves current settings on down
    }
};
