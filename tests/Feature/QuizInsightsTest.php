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

        $mockResponse = "Strong Areas:\n- Item 1: The student demonstrated an understanding of protective factors (APA, 2020, p. 123).\n\nWeak Areas:\n- Item 2: The student incorrectly identified developmental psychopathology (Wampold, 2001, p. 12).\n\nRecommendation:\n1. Re-read the principles of developmental psychopathology (APA, 2020, p. 145).\n2. Review case studies.";

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
}
