<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\Module;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\ItemAnalysis\ItemAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PsychometricItemAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function createTeacher(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Teacher User',
            'email' => 'teacher_'.uniqid().'@example.com',
            'idnumber' => 'TCH'.rand(1000, 9999),
            'password' => bcrypt('password'),
            'role' => 'teacher',
            'status' => 'active',
        ], $attributes));
    }

    private function createStudent(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Student User',
            'email' => 'student_'.uniqid().'@example.com',
            'idnumber' => 'STD'.rand(10000, 99999),
            'password' => bcrypt('password'),
            'role' => 'student',
            'status' => 'active',
        ], $attributes));
    }

    public function test_hand_computed_matrix_calculates_exact_p_d_and_flags(): void
    {
        $teacher = $this->createTeacher();
        $class = ClassModel::query()->create([
            'name' => 'Psychometrics 101',
            'code' => 'PSY101',
            'school_year' => 2026,
            'created_by' => $teacher->id,
        ]);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'Midterm Item Calibration',
            'is_quiz' => true,
        ]);

        // Create 5 questions with defined correct options and AI labels
        // Q1: Easy (Correct: A)
        $q1 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Question 1: Foundational knowledge',
            'options' => json_encode(['A' => 'Alpha', 'B' => 'Beta', 'C' => 'Gamma', 'D' => 'Delta']),
            'correct_option' => 'A',
            'difficulty' => 'Easy',
            'order' => 1,
            'points' => 1,
        ]);

        // Q2: Ideal discriminator (Correct: B)
        $q2 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Question 2: Core discriminator',
            'options' => json_encode(['A' => 'Alpha', 'B' => 'Beta', 'C' => 'Gamma', 'D' => 'Delta']),
            'correct_option' => 'B',
            'difficulty' => 'Average',
            'order' => 2,
            'points' => 1,
        ]);

        // Q3: Negative discriminator / Key alert (Correct: C)
        $q3 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Question 3: Flawed key test',
            'options' => json_encode(['A' => 'Alpha', 'B' => 'Beta', 'C' => 'Gamma', 'D' => 'Delta']),
            'correct_option' => 'C',
            'difficulty' => 'Difficult',
            'order' => 3,
            'points' => 1,
        ]);

        // Q4: Difficult item (Correct: D)
        $q4 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Question 4: Advanced mastery',
            'options' => json_encode(['A' => 'Alpha', 'B' => 'Beta', 'C' => 'Gamma', 'D' => 'Delta']),
            'correct_option' => 'D',
            'difficulty' => 'Difficult',
            'order' => 4,
            'points' => 1,
        ]);

        // Q5: Trap distractor on Option B (Correct: A)
        $q5 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Question 5: Trap distractor test',
            'options' => json_encode(['A' => 'Alpha', 'B' => 'Beta', 'C' => 'Gamma', 'D' => 'Delta']),
            'correct_option' => 'A',
            'difficulty' => 'Average',
            'order' => 5,
            'points' => 1,
        ]);

        // 10 students with explicit response patterns:
        // Top 3 (Upper 27% => g = 3): S1, S2, S3
        // Middle 4: S4, S5, S6, S7
        // Lower 3 (Lower 27% => g = 3): S8, S9, S10
        $responses = [
            // S1 (Score 5): Q1:A (ok), Q2:B (ok), Q3:C (ok), Q4:D (ok), Q5:A (ok)
            1 => ['answers' => ['A', 'B', 'C', 'D', 'A']],
            // S2 (Score 4): Q1:A (ok), Q2:B (ok), Q3:B (fail), Q4:D (ok), Q5:B (trap)
            2 => ['answers' => ['A', 'B', 'B', 'D', 'B']],
            // S3 (Score 3): Q1:A (ok), Q2:B (ok), Q3:B (fail), Q4:A (fail), Q5:B (trap)
            3 => ['answers' => ['A', 'B', 'B', 'A', 'B']],
            // S4 (Score 3): Q1:A (ok), Q2:B (ok), Q3:C (ok), Q4:A (fail), Q5:C (fail)
            4 => ['answers' => ['A', 'B', 'C', 'A', 'C']],
            // S5 (Score 2): Q1:A (ok), Q2:C (fail), Q3:C (ok), Q4:A (fail), Q5:C (fail)
            5 => ['answers' => ['A', 'C', 'C', 'A', 'C']],
            // S6 (Score 2): Q1:A (ok), Q2:C (fail), Q3:C (ok), Q4:B (fail), Q5:D (fail)
            6 => ['answers' => ['A', 'C', 'C', 'B', 'D']],
            // S7 (Score 2): Q1:A (ok), Q2:C (fail), Q3:C (ok), Q4:B (fail), Q5:D (fail)
            7 => ['answers' => ['A', 'C', 'C', 'B', 'D']],
            // S8 (Score 2): Q1:A (ok), Q2:C (fail), Q3:C (ok), Q4:C (fail), Q5:A (ok)
            8 => ['answers' => ['A', 'C', 'C', 'C', 'A']],
            // S9 (Score 1): Q1:A (ok), Q2:C (fail), Q3:C (ok-wait let's give C), Q4:C (fail), Q5:C (fail)
            9 => ['answers' => ['A', 'C', 'C', 'C', 'C']],
            // S10 (Score 1): Q1:B (fail), Q2:C (fail), Q3:C (ok), Q4:C (fail), Q5:D (fail)
            10 => ['answers' => ['B', 'C', 'C', 'C', 'D']],
        ];

        $questions = [$q1, $q2, $q3, $q4, $q5];

        foreach ($responses as $num => $data) {
            $student = $this->createStudent(['idnumber' => 'STD'.str_pad((string) $num, 4, '0', STR_PAD_LEFT)]);
            $class->students()->attach($student->id);

            $attempt = QuizAttempt::query()->create([
                'user_id' => $student->id,
                'module_id' => $module->id,
                'score' => 1,
                'total' => 5,
                'percentage' => 20,
                'passed' => false,
                'status' => 'completed',
                'created_at' => now()->subMinutes(60 - $num),
            ]);

            foreach ($data['answers'] as $qIdx => $selectedOption) {
                $q = $questions[$qIdx];
                QuizAnswer::query()->create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $q->id,
                    'selected_option' => $selectedOption,
                    'is_correct' => ($selectedOption === $q->correct_option),
                ]);
            }
        }

        // Run analysis via service
        $service = app(ItemAnalysisService::class);
        $result = $service->analyzeQuiz($module->id, null, true);

        // Examine test-level output
        $testStats = $result['test'];
        $this->assertEquals(10, $testStats['n_examinees']);
        $this->assertEquals(5, $testStats['n_items']);
        $this->assertNotNull($testStats['mean_score']);
        $this->assertNotNull($testStats['sd_score']);
        $this->assertNotNull($testStats['kr20']);

        // Examine item-level output
        $items = collect($result['items'])->keyBy('question_id');

        // Check Q1 (Foundational: 9 correct out of 10 => p = 0.90)
        $q1Stats = $items->get($q1->id);
        $this->assertEquals(0.90, (float) $q1Stats['p_index']);
        $this->assertEquals('Very Easy', $q1Stats['difficulty_interpretation']);

        // Check Q2 (Ideal discriminator: Upper = 3/3, Lower = 0/3 => D = 1.00)
        $q2Stats = $items->get($q2->id);
        $this->assertEquals(1.00, (float) $q2Stats['d_index']);
        $this->assertEquals('Very good', $q2Stats['discrimination_interpretation']);
        $this->assertEquals('retain', $q2Stats['verdict']);

        // Check Q3 (Flawed key: Upper = 1/3, Lower = 3/3 => D = -0.6667)
        $q3Stats = $items->get($q3->id);
        $this->assertLessThan(0, (float) $q3Stats['d_index']);
        $this->assertContains('negative_d', $q3Stats['flags']);
        $this->assertEquals('reject', $q3Stats['verdict']);

        // Check Q5 (Trap distractor on B: chosen by upper group (2/3) but 0 lower group)
        $q5Stats = $items->get($q5->id);
        $this->assertContains('problem_distractor:B', $q5Stats['flags']);

        // Verify persistence into database
        $this->assertDatabaseHas('test_analysis_results', [
            'module_id' => $module->id,
            'n_examinees' => 10,
        ]);

        $this->assertDatabaseHas('item_analysis_results', [
            'module_id' => $module->id,
            'question_id' => $q2->id,
            'verdict' => 'retain',
        ]);
    }

    public function test_insufficient_sample_size_sets_insufficient_confidence_and_verdict(): void
    {
        $teacher = $this->createTeacher();
        $class = ClassModel::query()->create([
            'name' => 'Small Class',
            'code' => 'SML101',
            'school_year' => 2026,
            'created_by' => $teacher->id,
        ]);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'Small Sample Quiz',
            'is_quiz' => true,
        ]);

        $q1 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Sample item',
            'options' => json_encode(['A' => 'A', 'B' => 'B']),
            'correct_option' => 'A',
            'difficulty' => 'Average',
            'order' => 1,
            'points' => 1,
        ]);

        // Only 4 students (< 10 threshold)
        for ($i = 1; $i <= 4; $i++) {
            $student = $this->createStudent();
            $class->students()->attach($student->id);

            $attempt = QuizAttempt::query()->create([
                'user_id' => $student->id,
                'module_id' => $module->id,
                'score' => 1,
                'total' => 1,
                'percentage' => 100,
                'passed' => true,
                'status' => 'completed',
            ]);

            QuizAnswer::query()->create([
                'attempt_id' => $attempt->id,
                'question_id' => $q1->id,
                'selected_option' => 'A',
                'is_correct' => true,
            ]);
        }

        $service = app(ItemAnalysisService::class);
        $result = $service->analyzeQuiz($module->id);

        $itemStats = $result['items'][0];
        $this->assertEquals('insufficient', $itemStats['confidence']);
        $this->assertEquals('insufficient', $itemStats['verdict']);
    }

    public function test_attempt_policy_first_ignores_retakes(): void
    {
        $teacher = $this->createTeacher();
        $class = ClassModel::query()->create([
            'name' => 'Retake Test Class',
            'code' => 'RET101',
            'school_year' => 2026,
            'created_by' => $teacher->id,
        ]);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'Retake Analysis',
            'is_quiz' => true,
        ]);

        $q1 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Capital of France?',
            'options' => json_encode(['A' => 'Paris', 'B' => 'Lyon']),
            'correct_option' => 'A',
            'difficulty' => 'Average',
            'order' => 1,
            'points' => 1,
        ]);

        $student = $this->createStudent();
        $class->students()->attach($student->id);

        // First attempt: student answered 'B' (Incorrect)
        $attempt1 = QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'score' => 0,
            'total' => 1,
            'percentage' => 0,
            'passed' => false,
            'status' => 'completed',
            'created_at' => now()->subHours(2),
        ]);
        QuizAnswer::query()->create([
            'attempt_id' => $attempt1->id,
            'question_id' => $q1->id,
            'selected_option' => 'B',
            'is_correct' => false,
        ]);

        // Second attempt: student answered 'A' (Correct)
        $attempt2 = QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'score' => 1,
            'total' => 1,
            'percentage' => 100,
            'passed' => true,
            'status' => 'completed',
            'created_at' => now()->subHour(),
        ]);
        QuizAnswer::query()->create([
            'attempt_id' => $attempt2->id,
            'question_id' => $q1->id,
            'selected_option' => 'A',
            'is_correct' => true,
        ]);

        $service = app(ItemAnalysisService::class);
        $result = $service->analyzeQuiz($module->id);

        // Because policy is 'first', attempt 1 is used (0 correct)
        $itemStats = $result['items'][0];
        $this->assertEquals(0, $itemStats['n_correct']);
        $this->assertEquals(0.00, (float) $itemStats['p_index']);
    }

    public function test_teacher_can_access_item_analysis_page(): void
    {
        $teacher = $this->createTeacher();
        $class = ClassModel::query()->create([
            'name' => 'Web Route Class',
            'code' => 'WRC101',
            'school_year' => 2026,
            'created_by' => $teacher->id,
        ]);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'Biology Exam',
            'is_quiz' => true,
        ]);

        $this->actingAs($teacher)
            ->get(route('modules.item-analysis', $module))
            ->assertStatus(200)
            ->assertSee('Psychometric Item Analysis')
            ->assertSee('Biology Exam');
    }

    public function test_unauthorized_teacher_cannot_access_other_teacher_item_analysis(): void
    {
        $teacher1 = $this->createTeacher();
        $teacher2 = $this->createTeacher();

        $class = ClassModel::query()->create([
            'name' => 'Private Class',
            'code' => 'PVT101',
            'school_year' => 2026,
            'created_by' => $teacher1->id,
        ]);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'Secret Exam',
            'is_quiz' => true,
        ]);

        $this->actingAs($teacher2)
            ->get(route('modules.item-analysis', $module))
            ->assertStatus(403);
    }

    public function test_api_run_analysis_returns_json(): void
    {
        $teacher = $this->createTeacher();
        $class = ClassModel::query()->create([
            'name' => 'API Class',
            'code' => 'API101',
            'school_year' => 2026,
            'created_by' => $teacher->id,
        ]);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'API Exam',
            'is_quiz' => true,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('modules.item-analysis.run', $module), [])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_artisan_command_runs_successfully(): void
    {
        $teacher = $this->createTeacher();
        $class = ClassModel::query()->create([
            'name' => 'CLI Class',
            'code' => 'CLI101',
            'school_year' => 2026,
            'created_by' => $teacher->id,
        ]);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'CLI Exam',
            'is_quiz' => true,
        ]);

        $this->artisan('item-analysis:run', [
            '--module' => $module->id,
        ])->assertSuccessful();
    }

    public function test_item_analysis_records_survive_question_deletion(): void
    {
        $teacher = $this->createTeacher();
        $class = ClassModel::query()->create([
            'name' => 'Archive Class',
            'code' => 'ARC101',
            'school_year' => 2026,
            'created_by' => $teacher->id,
        ]);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'Archive Exam',
            'is_quiz' => true,
        ]);

        $q = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Archivable question stem',
            'options' => json_encode(['A' => '1', 'B' => '2']),
            'correct_option' => 'A',
            'difficulty' => 'Easy',
            'order' => 1,
            'points' => 1,
        ]);

        $student = $this->createStudent();
        $class->students()->attach($student->id);

        $attempt = QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'score' => 1,
            'total' => 1,
            'percentage' => 100,
            'passed' => true,
            'status' => 'completed',
        ]);

        QuizAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'selected_option' => 'A',
            'is_correct' => true,
        ]);

        // Run analysis and persist
        app(ItemAnalysisService::class)->analyzeQuiz($module->id, null, true);

        $this->assertDatabaseHas('item_analysis_results', [
            'module_id' => $module->id,
            'question_id' => $q->id,
            'question_text' => 'Archivable question stem',
        ]);

        // Simulate question deletion (like during regeneration)
        $q->delete();

        // item_analysis_results row must still exist because question_id has no cascade delete
        $this->assertDatabaseHas('item_analysis_results', [
            'module_id' => $module->id,
            'question_text' => 'Archivable question stem',
        ]);
    }

    public function test_zero_variance_boundary_conditions(): void
    {
        $teacher = $this->createTeacher();
        $class = ClassModel::query()->create([
            'name' => 'Zero Variance Class',
            'code' => 'ZVC101',
            'school_year' => 2026,
            'created_by' => $teacher->id,
        ]);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'Trivial Exam',
            'is_quiz' => true,
        ]);

        // Q1: Everyone gets right (p = 1.0)
        $q1 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Trivial question',
            'options' => json_encode(['A' => 'Right', 'B' => 'Wrong']),
            'correct_option' => 'A',
            'difficulty' => 'Easy',
            'order' => 1,
            'points' => 1,
        ]);

        // Q2: Everyone gets right (p = 1.0)
        $q2 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Another trivial question',
            'options' => json_encode(['A' => 'Right', 'B' => 'Wrong']),
            'correct_option' => 'A',
            'difficulty' => 'Easy',
            'order' => 2,
            'points' => 1,
        ]);

        // 12 students all get perfect score (score = 2 for all examinees => variance = 0)
        for ($i = 1; $i <= 12; $i++) {
            $student = $this->createStudent();
            $class->students()->attach($student->id);

            $attempt = QuizAttempt::query()->create([
                'user_id' => $student->id,
                'module_id' => $module->id,
                'score' => 2,
                'total' => 2,
                'percentage' => 100,
                'passed' => true,
                'status' => 'completed',
            ]);

            QuizAnswer::query()->create([
                'attempt_id' => $attempt->id,
                'question_id' => $q1->id,
                'selected_option' => 'A',
                'is_correct' => true,
            ]);

            QuizAnswer::query()->create([
                'attempt_id' => $attempt->id,
                'question_id' => $q2->id,
                'selected_option' => 'A',
                'is_correct' => true,
            ]);
        }

        $result = app(ItemAnalysisService::class)->analyzeQuiz($module->id);

        $testStats = $result['test'];
        $this->assertEquals(0.00, (float) $testStats['sd_score']);
        $this->assertNull($testStats['kr20']);
        $this->assertEquals('Not computable (no score variance)', $testStats['kr20_interpretation']);

        $item1Stats = $result['items'][0];
        $this->assertEquals(1.00, (float) $item1Stats['p_index']);
        $this->assertEquals(0.00, (float) $item1Stats['d_index']);
        $this->assertNull($item1Stats['point_biserial']);
        $this->assertContains('all_correct', $item1Stats['flags']);
    }

    public function test_unassigned_questions_fallback_when_module_stage_is_set(): void
    {
        $teacher = $this->createTeacher();
        $class = ClassModel::query()->create([
            'name' => 'Fallback Class',
            'code' => 'FBC101',
            'school_year' => 2026,
            'created_by' => $teacher->id,
        ]);

        // Module has quiz_stage = 'post_test'
        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'Post-Test: Final Module Evaluation',
            'is_quiz' => true,
            'quiz_stage' => 'post_test',
        ]);

        // Questions have quiz_stage = null (e.g. from Test Bank or manual entry)
        $q1 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'quiz_stage' => null,
            'question_text' => 'Unassigned question stem',
            'options' => json_encode(['A' => 'Right', 'B' => 'Wrong']),
            'correct_option' => 'A',
            'difficulty' => 'Average',
            'order' => 1,
            'points' => 1,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            $student = $this->createStudent();
            $class->students()->attach($student->id);

            $attempt = QuizAttempt::query()->create([
                'user_id' => $student->id,
                'module_id' => $module->id,
                'quiz_stage' => 'post_test',
                'score' => 1,
                'total' => 1,
                'percentage' => 100,
                'passed' => true,
                'status' => 'completed',
            ]);

            QuizAnswer::query()->create([
                'attempt_id' => $attempt->id,
                'question_id' => $q1->id,
                'selected_option' => 'A',
                'is_correct' => true,
            ]);
        }

        // Running analysis with 'post_test' stage must NOT return 0 items
        $result = app(ItemAnalysisService::class)->analyzeQuiz($module->id, 'post_test');

        $this->assertEquals(1, $result['test']['n_items']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals($q1->id, $result['items'][0]['question_id']);
    }
}
