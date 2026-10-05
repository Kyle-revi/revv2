<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\MockBoard;
use App\Models\MockBoardAttempt;
use App\Models\MockBoardPhase;
use App\Models\Module;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockBoardInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_board_insights_uses_question_domain_and_generates_actionable_recommendations(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'program' => 'accountancy',
        ]);

        $student = User::factory()->create([
            'role' => 'student',
            'program' => 'accountancy',
        ]);

        $class = ClassModel::factory()->create([
            'program' => 'accountancy',
            'created_by' => $teacher->id,
        ]);
        $class->students()->attach($student->id);

        $board = MockBoard::create([
            'title' => 'CPALE Accountancy Mock Board 2026',
            'program' => 'accountancy',
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'passing_percentage' => 75,
            'review_period_start' => now()->subDays(5),
            'review_period_end' => now()->addDays(5),
            'status' => 'approved',
        ]);

        $module = Module::factory()->create([
            'is_quiz' => true,
            'is_formal_assessment' => false,
            'is_mock_board' => true,
        ]);

        $phase = MockBoardPhase::create([
            'mock_board_id' => $board->id,
            'phase_type' => 'pre_test',
            'sequence_number' => 1,
            'label' => 'Pre-Board Diagnostic',
            'title' => 'CPALE Pre-Board',
            'module_id' => $module->id,
            'is_same_questions' => true,
        ]);

        $qFar = QuizQuestion::create([
            'module_id' => $module->id,
            'question_text' => 'What is the standard measurement for inventory under PAS 2?',
            'options' => ['A' => 'Lower of cost and NRV', 'B' => 'Fair value', 'C' => 'Replacement cost', 'D' => 'Historical cost only'],
            'correct_option' => 'A',
            'points' => 1,
            'order' => 1,
            'domain' => 'Financial Accounting and Reporting (FAR)',
        ]);

        $qTax = QuizQuestion::create([
            'module_id' => $module->id,
            'question_text' => 'What is the corporate income tax rate under CREATE Act?',
            'options' => ['A' => '25%', 'B' => '30%', 'C' => '35%', 'D' => '20%'],
            'correct_option' => 'A',
            'points' => 1,
            'order' => 2,
            'domain' => 'Taxation',
        ]);

        $quizAttempt = QuizAttempt::create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'mock_board_id' => $board->id,
            'score' => 1,
            'total' => 2,
            'percentage' => 50,
            'passed' => false,
        ]);

        QuizAnswer::create([
            'attempt_id' => $quizAttempt->id,
            'question_id' => $qFar->id,
            'selected_option' => 'A',
            'is_correct' => true,
        ]);

        QuizAnswer::create([
            'attempt_id' => $quizAttempt->id,
            'question_id' => $qTax->id,
            'selected_option' => 'B',
            'is_correct' => false,
        ]);

        $mockAttempt = MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $phase->id,
            'quiz_attempt_id' => $quizAttempt->id,
            'phase_type' => 'pre_test',
            'score' => 1,
            'total_questions' => 2,
            'percentage' => 50,
            'passed' => false,
            'attempt_count' => 1,
        ]);

        $response = $this->actingAs($student)
            ->postJson(route('student.mock-boards.insights', [$board, $phase]));

        $response->assertOk()
            ->assertJsonStructure(['strong_areas', 'weak_areas', 'recommendation']);

        $data = $response->json();

        // Strong should contain FAR with 100% mastery
        $this->assertContains('Financial Accounting and Reporting (FAR) (100% Mastery)', $data['strong_areas']);

        // Weak should contain Taxation with 0% mastery
        $this->assertContains('Taxation (0% Mastery)', $data['weak_areas']);

        // Recommendation must cite Taxation, NOT "General Assessment"
        $this->assertStringContainsString('Taxation', $data['recommendation']);
        $this->assertStringNotContainsString('General Assessment', $data['recommendation']);
        $this->assertStringContainsString('Priority Review', $data['recommendation']);

        $mockAttempt->refresh();
        $this->assertStringContainsString('Financial Accounting and Reporting (FAR)', $mockAttempt->ai_strong);
        $this->assertStringContainsString('Taxation', $mockAttempt->ai_weak);
    }
}
