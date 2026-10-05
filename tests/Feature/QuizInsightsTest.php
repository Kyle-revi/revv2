<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\Module;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\CloudflareAI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class QuizInsightsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private ClassModel $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->class = ClassModel::factory()->create(['created_by' => $this->teacher->id]);
        $this->class->students()->attach($this->student->id);
    }

    public function test_generate_insights_returns_fallback_when_ai_call_fails(): void
    {
        $module = Module::factory()->create([
            'class_id' => $this->class->id,
            'is_quiz' => true,
            'is_formal_assessment' => false,
        ]);

        $attempt = QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $module->id,
            'score' => 1,
            'total' => 2,
            'percentage' => 50,
            'passed' => true,
        ]);

        $question = QuizQuestion::create([
            'module_id' => $module->id,
            'question_text' => 'What is bookkeeping?',
            'options' => ['A' => 'A process', 'B' => 'A place', 'C' => 'A person', 'D' => 'A color'],
            'correct_option' => 'A',
            'points' => 1,
            'order' => 1,
            'difficulty' => 'Normal',
        ]);

        QuizAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option' => 'B',
            'is_correct' => false,
        ]);

        $this->app->instance('App\\Services\\CloudflareAI', new class extends CloudflareAI
        {
            public function run(string $model, array $payload): array
            {
                throw new RuntimeException('AI service unavailable');
            }
        });

        $this->actingAs($this->student)
            ->postJson(route('quiz.insights', $module))
            ->assertOk()
            ->assertJsonPath('success', true);

        $attempt->refresh();
        $this->assertNotNull($attempt->ai_strong);
        $this->assertNotNull($attempt->ai_weak);
        $this->assertNotNull($attempt->ai_recommendation);
        $this->assertStringContainsString('Revisit the lecture notes', $attempt->ai_recommendation);
        $this->assertStringContainsString('What is bookkeeping?', $attempt->ai_recommendation);
    }

    public function test_generate_insights_parses_and_persists_successful_ai_response(): void
    {
        $module = Module::factory()->create([
            'class_id' => $this->class->id,
            'is_quiz' => true,
            'is_formal_assessment' => false,
        ]);

        $attempt = QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $module->id,
            'score' => 1,
            'total' => 2,
            'percentage' => 50,
            'passed' => true,
        ]);

        $question = QuizQuestion::create([
            'module_id' => $module->id,
            'question_text' => 'What is bookkeeping?',
            'options' => ['A' => 'A process', 'B' => 'A place', 'C' => 'A person', 'D' => 'A color'],
            'correct_option' => 'A',
            'points' => 1,
            'order' => 1,
            'difficulty' => 'Normal',
        ]);

        QuizAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option' => 'B',
            'is_correct' => false,
        ]);

        $mockResponse = "Strong Areas:\n- Demonstrated solid grasp of initial accounting entries.\n\nWeak Areas:\n- Needs review on defining bookkeeping vs accounting.\n\nRecommendation:\n1. Re-read lesson 1 definition of bookkeeping.\n2. Practice distinguishing recording vs reporting.\n3. Retake quiz.";

        $this->app->instance('App\\Services\\CloudflareAI', new class($mockResponse) extends CloudflareAI
        {
            public function __construct(private string $response = '') {}

            public function run(string $model, array $payload): array
            {
                return ['response' => $this->response];
            }
        });

        $this->actingAs($this->student)
            ->postJson(route('quiz.insights', $module))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('strong', '- Demonstrated solid grasp of initial accounting entries.')
            ->assertJsonPath('weak', '- Needs review on defining bookkeeping vs accounting.')
            ->assertJsonPath('recommendation', "1. Re-read lesson 1 definition of bookkeeping.\n2. Practice distinguishing recording vs reporting.\n3. Retake quiz.");

        $attempt->refresh();
        $this->assertSame('- Demonstrated solid grasp of initial accounting entries.', $attempt->ai_strong);
        $this->assertSame('- Needs review on defining bookkeeping vs accounting.', $attempt->ai_weak);
        $this->assertSame("1. Re-read lesson 1 definition of bookkeeping.\n2. Practice distinguishing recording vs reporting.\n3. Retake quiz.", $attempt->ai_recommendation);
    }

    public function test_generate_insights_returns_generic_fallback_when_attempt_is_missing(): void
    {
        $module = Module::factory()->create([
            'class_id' => $this->class->id,
            'is_quiz' => true,
            'is_formal_assessment' => false,
        ]);

        $this->actingAs($this->student)
            ->postJson(route('quiz.insights', $module))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('weak', 'Detailed answer analysis could not be loaded for this attempt.');
    }

    public function test_generate_insights_sanitizes_hallucinated_citations_and_converts_to_second_person(): void
    {
        $module = Module::factory()->create([
            'class_id' => $this->class->id,
            'is_quiz' => true,
            'is_formal_assessment' => false,
        ]);

        $attempt = QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $module->id,
            'score' => 1,
            'total' => 2,
            'percentage' => 50,
            'passed' => true,
        ]);

        $question = QuizQuestion::create([
            'module_id' => $module->id,
            'question_text' => 'What are protective factors?',
            'options' => ['A' => 'Strengths', 'B' => 'Weaknesses', 'C' => 'Diseases', 'D' => 'None'],
            'correct_option' => 'A',
            'points' => 1,
            'order' => 1,
            'difficulty' => 'Normal',
        ]);

        QuizAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option' => 'A',
            'is_correct' => true,
        ]);

        $mockResponse = "Strong Areas:\n- Item 1: The student demonstrated an understanding of protective factors (APA, 2020, p. 123) and understanding the correct principle of assessing behavior in isolation from systemic factors. You demonstrated mastery of the concept that behavior should be assessed in isolation from systemic factors. You correctly applied the principle that behavior should be evaluated independently of systemic influences. They have shown solid grasp, which will help them succeed. This shows that You have grasped the core idea.\n\nWeak Areas:\n- Item 2: The student incorrectly identified developmental psychopathology (Wampold, 2001, p. 12). They selected an answer that was incomplete. you selected options prematurely. You needs to practice more because You struggles with their assessments. Review cultural norms and their variability, and practice how to distinguish them.\n\nRecommendation:\n1. Re-read the principles of developmental psychopathology (APA, 2020, p. 145) to solidify their understanding.\n2. To prevent repeating the mistake, You should practice case studies.\n3. Before retaking the test, You should review cultural norms.";

        $this->app->instance('App\\Services\\CloudflareAI', new class($mockResponse) extends CloudflareAI
        {
            public function __construct(private string $response = '') {}

            public function run(string $model, array $payload): array
            {
                return ['response' => $this->response];
            }
        });

        $res = $this->actingAs($this->student)
            ->postJson(route('quiz.insights', $module))
            ->assertOk()
            ->assertJsonPath('success', true);

        $strong = (string) $res->json('strong');
        $weak = (string) $res->json('weak');
        $rec = (string) $res->json('recommendation');

        // Verify citations are completely stripped
        $this->assertStringNotContainsString('(APA, 2020, p. 123)', $strong);
        $this->assertStringNotContainsString('(Wampold, 2001, p. 12)', $weak);
        $this->assertStringNotContainsString('(APA, 2020, p. 145)', $rec);

        // Verify third-person "The student" is converted to "You"
        $this->assertStringNotContainsString('The student demonstrated', $strong);
        $this->assertStringContainsString('You demonstrated', $strong);
        $this->assertStringNotContainsString('The student incorrectly', $weak);
        $this->assertStringContainsString('You incorrectly', $weak);

        // Verify pronoun conversions and preservation of concept nouns
        $this->assertStringContainsString('You have shown', $strong);
        $this->assertStringContainsString('help you succeed', $strong);
        $this->assertStringContainsString('that you have grasped', $strong);
        $this->assertStringContainsString('their variability', $weak);
        $this->assertStringContainsString('distinguish them', $weak);
        $this->assertStringContainsString('solidify your understanding', $rec);

        // Verify verb conjugations and assessments noun
        $this->assertStringContainsString('You need to practice', $weak);
        $this->assertStringNotContainsString('You needs', $weak);
        $this->assertStringContainsString('you struggle', $weak);
        $this->assertStringNotContainsString('You struggles', $weak);
        $this->assertStringContainsString('your assessments', $weak);
        $this->assertStringNotContainsString('their assessments', $weak);

        // Verify safeguard against inverted isolated assessment claim
        $this->assertStringNotContainsString('assessing behavior in isolation', $strong);
        $this->assertStringNotContainsString('behavior should be assessed in isolation', $strong);
        $this->assertStringNotContainsString('evaluated independently of systemic', $strong);
        $this->assertStringContainsString('behavior must be assessed in relation to systemic', $strong);
        $this->assertStringContainsString('behavior must be evaluated in relation to systemic', $strong);

        // Verify sentence-start capitalization for "You"
        $this->assertStringContainsString('You selected', $weak);
        $this->assertStringNotContainsString('. you selected', $weak);

        // Verify recommendation padding cleanup (imperative steps)
        $this->assertStringContainsString('2. Practice case studies', $rec);
        $this->assertStringContainsString('3. Review cultural norms', $rec);
        $this->assertStringNotContainsString('You should', $rec);

        // Verify "Item 1:" / "Item 2:" prefixes are removed
        $this->assertStringNotContainsString('Item 1:', $strong);
        $this->assertStringNotContainsString('Item 2:', $weak);
    }

    public function test_generate_insights_invalidates_legacy_hallucinated_cache(): void
    {
        $module = Module::factory()->create([
            'class_id' => $this->class->id,
            'is_quiz' => true,
            'is_formal_assessment' => false,
        ]);

        // Attempt pre-populated with old legacy hallucinated citations
        $attempt = QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $module->id,
            'score' => 1,
            'total' => 2,
            'percentage' => 50,
            'passed' => true,
            'ai_strong' => 'Item 1: The student demonstrated an understanding (APA, 2020, p. 123).',
            'ai_weak' => 'Item 2: The student incorrectly stated cultural norms (Triandis, 1995, p. 23).',
            'ai_recommendation' => 'Revisit the concept (APA, 2020, p. 145).',
        ]);

        $freshResponse = "Strong Areas:\n- **Protective Factors:** You demonstrated solid understanding.\n\nWeak Areas:\n- **Cultural Norms:** Review variability across cultures.\n\nRecommendation:\n1. Revisit module notes on cultural norms.";

        $this->app->instance('App\\Services\\CloudflareAI', new class($freshResponse) extends CloudflareAI
        {
            public function __construct(private string $response = '') {}

            public function run(string $model, array $payload): array
            {
                return ['response' => $this->response];
            }
        });

        $this->actingAs($this->student)
            ->postJson(route('quiz.insights', $module))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('strong', '- **Protective Factors:** You demonstrated solid understanding.');

        $attempt->refresh();
        $this->assertSame('- **Protective Factors:** You demonstrated solid understanding.', $attempt->ai_strong);
    }

    public function test_generate_insights_caps_bullets_to_maximum_three_items(): void
    {
        $module = Module::factory()->create([
            'class_id' => $this->class->id,
            'is_quiz' => true,
            'is_formal_assessment' => false,
        ]);

        $attempt = QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $module->id,
            'score' => 1,
            'total' => 10,
            'percentage' => 10,
            'passed' => false,
        ]);

        $question = QuizQuestion::create([
            'module_id' => $module->id,
            'question_text' => 'What is abnormal behavior?',
            'options' => ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D'],
            'correct_option' => 'A',
            'points' => 1,
            'order' => 1,
            'difficulty' => 'Normal',
        ]);

        QuizAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option' => 'B',
            'is_correct' => false,
        ]);

        // Mock an AI response that produces 9 bullets in weak areas and 9 in recommendation
        $excessiveResponse = "Strong Areas:\n- **Theme 1:** Mastered concept.\n- **Theme 2:** Mastered concept.\n- **Theme 3:** Too many.\n\nWeak Areas:\n- Misunderstanding 1\n- Misunderstanding 2\n- Misunderstanding 3\n- Misunderstanding 4\n- Misunderstanding 5\n- Misunderstanding 6\n- Misunderstanding 7\n- Misunderstanding 8\n- Misunderstanding 9\n\nRecommendation:\n1. Step 1\n2. Step 2\n3. Step 3\n4. Step 4\n5. Step 5\n6. Step 6\n7. Step 7\n8. Step 8\n9. Step 9\n\nActionable Next Study Step: Extra chatter to strip.";

        $this->app->instance('App\\Services\\CloudflareAI', new class($excessiveResponse) extends CloudflareAI
        {
            public function __construct(private string $response = '') {}

            public function run(string $model, array $payload): array
            {
                return ['response' => $this->response];
            }
        });

        $res = $this->actingAs($this->student)
            ->postJson(route('quiz.insights', $module))
            ->assertOk()
            ->assertJsonPath('success', true);

        $weak = (string) $res->json('weak');
        $strong = (string) $res->json('strong');
        $rec = (string) $res->json('recommendation');

        // Strong should have at most 2 bullets
        $this->assertStringNotContainsString('Theme 3', $strong);
        // Weak should have at most 3 bullets
        $this->assertStringContainsString('Misunderstanding 3', $weak);
        $this->assertStringNotContainsString('Misunderstanding 4', $weak);
        // Recommendation should have at most 3 numbered items
        $this->assertStringContainsString('3. Step 3', $rec);
        $this->assertStringNotContainsString('4. Step 4', $rec);
        // Extra chatter should be stripped
        $this->assertStringNotContainsString('Actionable Next Study Step', $rec);
    }

    public function test_generate_insights_invalidates_cache_with_verbatim_question_quotes(): void
    {
        $module = Module::factory()->create([
            'class_id' => $this->class->id,
            'is_quiz' => true,
            'is_formal_assessment' => false,
        ]);

        $attempt = QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $module->id,
            'score' => 1,
            'total' => 2,
            'percentage' => 50,
            'passed' => true,
            'ai_strong' => '- **Cultural Influence:** You correctly answered in the question "How do cultural norms influence abnormal behavior?" where you selected "What is normal".',
            'ai_weak' => '- **Predisposing Factors:** Your answer in the question "Why are biological factors predisposing?" was incorrect.',
            'ai_recommendation' => '1. Review predisposing factors.',
        ]);

        $freshResponse = "Strong Areas:\n- **Cultural Influence:** You showed a clear grasp of cultural relativism.\n\nWeak Areas:\n- **Predisposing Factors:** Clarify how biological vulnerabilities increase risk.\n\nRecommendation:\n1. Review chapter on predisposing factors.";

        $this->app->instance('App\\Services\\CloudflareAI', new class($freshResponse) extends CloudflareAI
        {
            public function __construct(private string $response = '') {}

            public function run(string $model, array $payload): array
            {
                return ['response' => $this->response];
            }
        });

        $this->actingAs($this->student)
            ->postJson(route('quiz.insights', $module))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('strong', '- **Cultural Influence:** You showed a clear grasp of cultural relativism.');

        $attempt->refresh();
        $this->assertSame('- **Cultural Influence:** You showed a clear grasp of cultural relativism.', $attempt->ai_strong);
    }

    public function test_generate_insights_force_refresh_bypasses_valid_cache(): void
    {
        $module = Module::factory()->create([
            'class_id' => $this->class->id,
            'is_quiz' => true,
            'is_formal_assessment' => false,
        ]);

        $attempt = QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $module->id,
            'score' => 1,
            'total' => 2,
            'percentage' => 50,
            'passed' => true,
            'ai_strong' => '- **Previous Concept:** Already cached.',
            'ai_weak' => '- **Previous Weakness:** Already cached.',
            'ai_recommendation' => '1. Previous recommendation.',
        ]);

        $refreshedResponse = "Strong Areas:\n- **New Concept:** Freshly generated insight.\n\nWeak Areas:\n- **New Weakness:** Freshly generated insight.\n\nRecommendation:\n1. Fresh action step.";

        $this->app->instance('App\\Services\\CloudflareAI', new class($refreshedResponse) extends CloudflareAI
        {
            public function __construct(private string $response = '') {}

            public function run(string $model, array $payload): array
            {
                return ['response' => $this->response];
            }
        });

        // Calling without force_refresh should return cached
        $this->actingAs($this->student)
            ->postJson(route('quiz.insights', $module))
            ->assertOk()
            ->assertJsonPath('strong', '- **Previous Concept:** Already cached.');

        // Calling with force_refresh should re-run AI and update
        $this->actingAs($this->student)
            ->postJson(route('quiz.insights', $module), ['force_refresh' => 1])
            ->assertOk()
            ->assertJsonPath('strong', '- **New Concept:** Freshly generated insight.');

        $attempt->refresh();
        $this->assertSame('- **New Concept:** Freshly generated insight.', $attempt->ai_strong);
    }
}
