<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\HistoricalBoardExamResult;
use App\Models\MockBoard;
use App\Models\MockBoardAttempt;
use App\Models\MockBoardPhase;
use App\Models\MockBoardReadinessReport;
use App\Models\Module;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\MockBoardReadinessService;
use App\Services\MockBoardStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockBoardReadinessReportTest extends TestCase
{
    use RefreshDatabase;

    private function createBoardAndStudent(string $program = 'accountancy'): array
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'program' => $program,
        ]);

        $student = User::factory()->create([
            'role' => 'student',
            'program' => $program,
        ]);

        $class = ClassModel::factory()->create([
            'program' => $program,
            'created_by' => $teacher->id,
        ]);
        $class->students()->attach($student->id);

        $board = MockBoard::create([
            'title' => 'CPALE Accountancy Mock Board 2026',
            'program' => $program,
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'passing_percentage' => 75,
            'review_period_start' => now()->subDays(5),
            'review_period_end' => now()->addDays(5),
            'status' => 'approved',
        ]);

        $preTestModule = Module::factory()->create([
            'is_quiz' => true,
            'is_formal_assessment' => false,
            'is_mock_board' => true,
        ]);

        $postTestModule = Module::factory()->create([
            'is_quiz' => true,
            'is_formal_assessment' => false,
            'is_mock_board' => true,
        ]);

        $preTestPhase = MockBoardPhase::create([
            'mock_board_id' => $board->id,
            'phase_type' => 'pre_test',
            'sequence_number' => 1,
            'label' => 'Pre-Test Diagnostic',
            'title' => 'Pre-Test Diagnostic',
            'module_id' => $preTestModule->id,
        ]);

        $postTestPhase = MockBoardPhase::create([
            'mock_board_id' => $board->id,
            'phase_type' => 'pre_boards',
            'sequence_number' => 2,
            'label' => 'Final Post-Test',
            'title' => 'Final Post-Test',
            'module_id' => $postTestModule->id,
        ]);

        return compact('teacher', 'student', 'class', 'board', 'preTestModule', 'postTestModule', 'preTestPhase', 'postTestPhase');
    }

    public function test_student_cannot_access_readiness_report_before_completing_both_phases(): void
    {
        $data = $this->createBoardAndStudent();
        $student = $data['student'];
        $board = $data['board'];

        // Case 1: No attempts at all
        $response = $this->actingAs($student)->get(route('student.mock-boards.readiness', $board));
        $response->assertRedirect(route('student.mock-boards.results', $board));
        $response->assertSessionHas('error');

        // Case 2: Only pre-test attempt completed
        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['preTestPhase']->id,
            'phase_type' => 'pre_test',
            'score' => 50,
            'total' => 100,
            'percentage' => 50,
            'passed' => false,
            'attempt_count' => 1,
        ]);

        $response = $this->actingAs($student)->get(route('student.mock-boards.readiness', $board));
        $response->assertRedirect(route('student.mock-boards.results', $board));
    }

    public function test_student_can_view_readiness_report_after_completing_both_phases(): void
    {
        $data = $this->createBoardAndStudent();
        $student = $data['student'];
        $board = $data['board'];

        // Complete pre-test attempt
        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['preTestPhase']->id,
            'phase_type' => 'pre_test',
            'score' => 55,
            'total' => 100,
            'percentage' => 55,
            'passed' => false,
            'attempt_count' => 1,
        ]);

        // Complete post-test attempt
        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['postTestPhase']->id,
            'phase_type' => 'pre_boards',
            'score' => 80,
            'total' => 100,
            'percentage' => 80,
            'passed' => true,
            'attempt_count' => 1,
        ]);

        $response = $this->actingAs($student)->get(route('student.mock-boards.readiness', $board));

        $response->assertOk();
        $response->assertViewIs('pages.student.mock-boards.readiness');
        $response->assertViewHas('report');
        $response->assertSee('Board Readiness Score');
        $response->assertSee('Diagnostic Growth');
        $response->assertSee('High Chance (Board Ready)');

        // Verify report was persisted in mock_board_readiness_reports table
        $this->assertDatabaseHas('mock_board_readiness_reports', [
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'tier' => 'high',
        ]);
    }

    public function test_readiness_tier_calculation_is_centralized_and_accurate(): void
    {
        // High tier (>= 75%)
        $high = MockBoardStatisticsService::calculateReadinessTier(78.5, 75);
        $this->assertSame('high', $high['tier']);
        $this->assertSame(0.0, $high['gap']);
        $this->assertStringContainsString('Board Ready', $high['label']);

        // Moderate tier (>= 65% when threshold is 75%)
        $mod = MockBoardStatisticsService::calculateReadinessTier(68.0, 75);
        $this->assertSame('moderate', $mod['tier']);
        $this->assertSame(7.0, $mod['gap']);
        $this->assertStringContainsString('Almost Ready', $mod['label']);

        // Low tier (< 65%)
        $low = MockBoardStatisticsService::calculateReadinessTier(52.0, 75);
        $this->assertSame('low', $low['tier']);
        $this->assertSame(23.0, $low['gap']);
        $this->assertStringContainsString('At-Risk', $low['label']);
    }

    public function test_domain_mastery_and_item_insights_detection(): void
    {
        $data = $this->createBoardAndStudent();
        $student = $data['student'];
        $board = $data['board'];

        // Create questions with domains
        $qFar1 = QuizQuestion::create([
            'module_id' => $data['preTestModule']->id,
            'question_text' => 'What is the asset definition under Conceptual Framework?',
            'domain' => 'FAR',
            'correct_option' => 'A',
            'options' => ['A' => 'Present economic resource', 'B' => 'Past obligation'],
            'explanation' => 'Conceptual Framework 2018 definition.',
        ]);

        $qFar2 = QuizQuestion::create([
            'module_id' => $data['preTestModule']->id,
            'question_text' => 'What is the inventory valuation rule under PAS 2?',
            'domain' => 'FAR',
            'correct_option' => 'A',
            'options' => ['A' => 'Lower of cost and NRV', 'B' => 'Fair value less costs'],
            'explanation' => 'PAS 2 requires LCNRV.',
        ]);

        $qTax = QuizQuestion::create([
            'module_id' => $data['preTestModule']->id,
            'question_text' => 'What is the standard VAT rate in the Philippines?',
            'domain' => 'TAX',
            'correct_option' => 'B',
            'options' => ['A' => '10%', 'B' => '12%'],
            'explanation' => 'NIRC Section 106 defines 12% VAT.',
        ]);

        // Pre-test attempt answers
        $preQuizAttempt = QuizAttempt::create([
            'user_id' => $student->id,
            'module_id' => $data['preTestModule']->id,
            'mock_board_id' => $board->id,
            'score' => 2,
            'total' => 3,
            'percentage' => 67,
            'passed' => false,
            'status' => 'completed',
        ]);

        // qFar1: correct in pre-test
        QuizAnswer::create(['attempt_id' => $preQuizAttempt->id, 'question_id' => $qFar1->id, 'selected_option' => 'A', 'is_correct' => true]);
        // qFar2: correct in pre-test
        QuizAnswer::create(['attempt_id' => $preQuizAttempt->id, 'question_id' => $qFar2->id, 'selected_option' => 'A', 'is_correct' => true]);
        // qTax: incorrect in pre-test
        QuizAnswer::create(['attempt_id' => $preQuizAttempt->id, 'question_id' => $qTax->id, 'selected_option' => 'A', 'is_correct' => false]);

        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['preTestPhase']->id,
            'phase_type' => 'pre_test',
            'quiz_attempt_id' => $preQuizAttempt->id,
            'score' => 2,
            'total' => 3,
            'percentage' => 67,
            'passed' => false,
            'attempt_count' => 1,
        ]);

        // Post-test attempt answers
        $postQuizAttempt = QuizAttempt::create([
            'user_id' => $student->id,
            'module_id' => $data['postTestModule']->id,
            'mock_board_id' => $board->id,
            'score' => 1,
            'total' => 3,
            'percentage' => 33,
            'passed' => false,
            'status' => 'completed',
        ]);

        // qFar1: correct in post-test
        QuizAnswer::create(['attempt_id' => $postQuizAttempt->id, 'question_id' => $qFar1->id, 'selected_option' => 'A', 'is_correct' => true]);
        // qFar2: INCORRECT in post-test (REGRESSION!)
        QuizAnswer::create(['attempt_id' => $postQuizAttempt->id, 'question_id' => $qFar2->id, 'selected_option' => 'B', 'is_correct' => false]);
        // qTax: INCORRECT in post-test (REPEATEDLY MISSED!)
        QuizAnswer::create(['attempt_id' => $postQuizAttempt->id, 'question_id' => $qTax->id, 'selected_option' => 'A', 'is_correct' => false]);

        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['postTestPhase']->id,
            'phase_type' => 'pre_boards',
            'quiz_attempt_id' => $postQuizAttempt->id,
            'score' => 1,
            'total' => 3,
            'percentage' => 33,
            'passed' => false,
            'attempt_count' => 1,
        ]);

        $response = $this->actingAs($student)->get(route('student.mock-boards.readiness', $board));
        $response->assertOk();

        $report = MockBoardReadinessReport::where('user_id', $student->id)->where('mock_board_id', $board->id)->first();
        $this->assertNotNull($report);

        // Check regressed items
        $regressed = $report->item_insights['regressed_items'] ?? [];
        $this->assertNotEmpty($regressed);
        $this->assertStringContainsString('inventory valuation', $regressed[0]['stem']);

        // Check repeatedly missed items
        $repeated = $report->item_insights['repeatedly_missed_items'] ?? [];
        $this->assertNotEmpty($repeated);
        $this->assertStringContainsString('VAT rate', $repeated[0]['stem']);
    }

    public function test_excel_export_streams_csv_attachment_with_utf8_bom(): void
    {
        $data = $this->createBoardAndStudent();
        $student = $data['student'];
        $board = $data['board'];

        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['preTestPhase']->id,
            'phase_type' => 'pre_test',
            'score' => 60,
            'total' => 100,
            'percentage' => 60,
            'passed' => false,
            'attempt_count' => 1,
        ]);

        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['postTestPhase']->id,
            'phase_type' => 'pre_boards',
            'score' => 76,
            'total' => 100,
            'percentage' => 76,
            'passed' => true,
            'attempt_count' => 1,
        ]);

        $response = $this->actingAs($student)->get(route('student.mock-boards.readiness.export', $board));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('REVISO - MOCK BOARD STUDENT READINESS REPORT', $content);
        $this->assertStringContainsString($student->name, $content);
        $this->assertStringContainsString('76%', $content);
    }

    public function test_student_from_different_program_cannot_view_readiness(): void
    {
        $data = $this->createBoardAndStudent('accountancy');
        $board = $data['board'];

        $differentProgramStudent = User::factory()->create([
            'role' => 'student',
            'program' => 'education',
        ]);

        $response = $this->actingAs($differentProgramStudent)->get(route('student.mock-boards.readiness', $board));
        $response->assertForbidden();
    }

    public function test_peer_benchmark_and_historical_comparison_are_displayed(): void
    {
        $data = $this->createBoardAndStudent('accountancy');
        $student = $data['student'];
        $board = $data['board'];

        // Link historical exam
        $historical = HistoricalBoardExamResult::create([
            'program' => 'accountancy',
            'exam_label' => 'CPALE Licensure Exam',
            'exam_period_or_year' => 'October 2025',
            'total_examinees' => 1000,
            'passed_count' => 312,
            'source_note' => 'Official PRC Release',
            'entered_by' => $data['teacher']->id,
        ]);
        $board->update(['historical_board_exam_result_id' => $historical->id]);

        // Student's own attempts
        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['preTestPhase']->id,
            'phase_type' => 'pre_test',
            'score' => 60,
            'total' => 100,
            'percentage' => 60,
            'passed' => false,
            'attempt_count' => 1,
        ]);
        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['postTestPhase']->id,
            'phase_type' => 'pre_boards',
            'score' => 85,
            'total' => 100,
            'percentage' => 85,
            'passed' => true,
            'attempt_count' => 1,
        ]);

        // Add 2 other peer students
        $peer1 = User::factory()->create(['role' => 'student', 'program' => 'accountancy']);
        $peer2 = User::factory()->create(['role' => 'student', 'program' => 'accountancy']);

        MockBoardAttempt::create([
            'user_id' => $peer1->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['postTestPhase']->id,
            'phase_type' => 'pre_boards',
            'score' => 65,
            'total' => 100,
            'percentage' => 65,
            'passed' => false,
            'attempt_count' => 1,
        ]);

        MockBoardAttempt::create([
            'user_id' => $peer2->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['postTestPhase']->id,
            'phase_type' => 'pre_boards',
            'score' => 75,
            'total' => 100,
            'percentage' => 75,
            'passed' => true,
            'attempt_count' => 1,
        ]);

        $response = $this->actingAs($student)->get(route('student.mock-boards.readiness', $board));

        $response->assertOk();
        $response->assertSee('Cohort Standing');
        $response->assertSee('Batch Average');
        $response->assertSee('Percentile Standing');
        $response->assertSee('CPALE Licensure Exam');
        $response->assertSee('31.2%'); // Historical national passing rate: 312 / 1000 = 31.2%
    }

    public function test_results_page_shows_cta_button_when_board_is_completed(): void
    {
        $data = $this->createBoardAndStudent('accountancy');
        $student = $data['student'];
        $board = $data['board'];

        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['preTestPhase']->id,
            'phase_type' => 'pre_test',
            'score' => 60,
            'total' => 100,
            'percentage' => 60,
            'passed' => false,
            'attempt_count' => 1,
        ]);

        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['postTestPhase']->id,
            'phase_type' => 'pre_boards',
            'score' => 80,
            'total' => 100,
            'percentage' => 80,
            'passed' => true,
            'attempt_count' => 1,
        ]);

        $response = $this->actingAs($student)->get(route('student.mock-boards.results', $board));

        $response->assertOk();
        $response->assertSee('View Readiness Report');
        $response->assertSee(route('student.mock-boards.readiness', $board));
    }

    public function test_all_100_percent_domains_do_not_produce_priority_focus_area(): void
    {
        $data = $this->createBoardAndStudent();
        $student = $data['student'];
        $board = $data['board'];

        $qAudit = QuizQuestion::create([
            'module_id' => $data['preTestModule']->id,
            'question_text' => 'Auditing Question 1',
            'domain' => 'Auditing and Assurance',
            'correct_option' => 'A',
            'options' => ['A' => 'Correct', 'B' => 'Wrong'],
        ]);

        $qFar = QuizQuestion::create([
            'module_id' => $data['preTestModule']->id,
            'question_text' => 'FAR Question 1',
            'domain' => 'Financial Accounting and Reporting',
            'correct_option' => 'A',
            'options' => ['A' => 'Correct', 'B' => 'Wrong'],
        ]);

        $preQuizAttempt = QuizAttempt::create([
            'user_id' => $student->id,
            'module_id' => $data['preTestModule']->id,
            'mock_board_id' => $board->id,
            'score' => 2,
            'total' => 2,
            'percentage' => 100,
            'passed' => true,
            'status' => 'completed',
        ]);
        QuizAnswer::create(['attempt_id' => $preQuizAttempt->id, 'question_id' => $qAudit->id, 'selected_option' => 'A', 'is_correct' => true]);
        QuizAnswer::create(['attempt_id' => $preQuizAttempt->id, 'question_id' => $qFar->id, 'selected_option' => 'A', 'is_correct' => true]);

        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['preTestPhase']->id,
            'phase_type' => 'pre_test',
            'quiz_attempt_id' => $preQuizAttempt->id,
            'score' => 2,
            'total' => 2,
            'percentage' => 100,
            'passed' => true,
            'attempt_count' => 1,
        ]);

        $postQuizAttempt = QuizAttempt::create([
            'user_id' => $student->id,
            'module_id' => $data['postTestModule']->id,
            'mock_board_id' => $board->id,
            'score' => 2,
            'total' => 2,
            'percentage' => 100,
            'passed' => true,
            'status' => 'completed',
        ]);
        QuizAnswer::create(['attempt_id' => $postQuizAttempt->id, 'question_id' => $qAudit->id, 'selected_option' => 'A', 'is_correct' => true]);
        QuizAnswer::create(['attempt_id' => $postQuizAttempt->id, 'question_id' => $qFar->id, 'selected_option' => 'A', 'is_correct' => true]);

        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'mock_board_phase_id' => $data['postTestPhase']->id,
            'phase_type' => 'pre_boards',
            'quiz_attempt_id' => $postQuizAttempt->id,
            'score' => 2,
            'total' => 2,
            'percentage' => 100,
            'passed' => true,
            'attempt_count' => 1,
        ]);

        $response = $this->actingAs($student)->get(route('student.mock-boards.readiness', $board));
        $response->assertOk();
        $response->assertDontSee('Priority Focus Area:');
        $response->assertSee('All Domains Mastered (100%)');
        $response->assertSee('No conceptual gaps identified');
    }

    public function test_action_plan_sanitizes_hallucinated_data_for_mastered_student(): void
    {
        $data = $this->createBoardAndStudent();
        $student = $data['student'];
        $board = $data['board'];

        $report = MockBoardReadinessReport::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'readiness_percentage' => 100.0,
            'tier' => 'tier_1',
            'tier_label' => 'High Readiness',
            'gap_percentage' => 0.0,
            'gap_items' => 0,
            'pre_test_score' => 70.0,
            'post_test_score' => 100.0,
            'improvement_percentage' => 30.0,
            'consistency_status' => 'Consistent',
            'domain_breakdown' => [
                'list' => [
                    ['domain' => 'Auditing and Assurance', 'post_score' => 100],
                    ['domain' => 'Financial Accounting and Reporting', 'post_score' => 100],
                ],
                'weakest_domain' => 'Auditing and Assurance', // Simulated legacy bad data
            ],
            'ai_action_plan' => [
                'priority_domains' => ['Financial Accounting', 'Auditing and Attestation', 'Regulation and Ethics'],
                'review_topics' => ['Financial Statement Analysis', 'Audit Planning and Risk Assessment'],
                'study_steps' => [
                    'Review ASC 606 and ASC 842 leases.',
                    'AICPA regulation and ethics standards.',
                ],
                'summary_narrative' => 'Congratulations on 100% score.',
            ],
            'generated_at' => now(),
        ]);

        // Model accessor should automatically sanitize
        $this->assertNull($report->domain_breakdown['weakest_domain']);
        $this->assertEmpty($report->ai_action_plan['priority_domains']);
        $this->assertEmpty($report->ai_action_plan['review_topics']);
        $this->assertStringNotContainsString('ASC 606', implode(' ', $report->ai_action_plan['study_steps']));
        $this->assertStringContainsString('Spaced Retrieval', implode(' ', $report->ai_action_plan['study_steps']));
    }

    public function test_student_with_low_score_has_priority_focus_area_and_weakest_domain(): void
    {
        $data = $this->createBoardAndStudent();
        $student = $data['student'];
        $board = $data['board'];
        $preModule = $data['preTestModule'];
        $postModule = $data['postTestModule'];

        // Pre-test questions
        for ($i = 1; $i <= 5; $i++) {
            QuizQuestion::create([
                'module_id' => $preModule->id,
                'question_text' => "Pre FAR Q{$i}",
                'options' => ['A' => 'Opt A', 'B' => 'Opt B'],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'order' => $i,
            ]);
            QuizQuestion::create([
                'module_id' => $preModule->id,
                'question_text' => "Pre Auditing Q{$i}",
                'options' => ['A' => 'Opt A', 'B' => 'Opt B'],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'order' => $i + 5,
            ]);
        }

        // Post-test questions
        $postQuestions = [];
        for ($i = 1; $i <= 5; $i++) {
            $postQuestions[] = QuizQuestion::create([
                'module_id' => $postModule->id,
                'question_text' => "Post FAR Q{$i}",
                'options' => ['A' => 'Opt A', 'B' => 'Opt B'],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'order' => $i,
            ]);
        }
        for ($i = 1; $i <= 5; $i++) {
            $postQuestions[] = QuizQuestion::create([
                'module_id' => $postModule->id,
                'question_text' => "Post Auditing Q{$i}",
                'options' => ['A' => 'Opt A', 'B' => 'Opt B'],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'order' => $i + 5,
            ]);
        }

        // Pre-test attempt: 50%
        $preAttempt = QuizAttempt::create([
            'user_id' => $student->id,
            'module_id' => $preModule->id,
            'mock_board_id' => $board->id,
            'score' => 5,
            'total' => 10,
            'percentage' => 50.0,
            'passed' => false,
            'status' => 'completed',
        ]);
        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'phase_type' => 'pre_test',
            'quiz_attempt_id' => $preAttempt->id,
            'score' => 5,
            'total' => 10,
            'percentage' => 50.0,
            'passed' => false,
        ]);

        // Post-test attempt: 3/10 (30%) -> FAR: 2/5 (40%), Auditing: 1/5 (20%)
        $postAttempt = QuizAttempt::create([
            'user_id' => $student->id,
            'module_id' => $postModule->id,
            'mock_board_id' => $board->id,
            'score' => 3,
            'total' => 10,
            'percentage' => 30.0,
            'passed' => false,
            'status' => 'completed',
        ]);

        // Student answers: correct for Q0, Q2 (FAR) and Q5 (Auditing)
        $correctIndices = [0, 2, 5];
        foreach ($postQuestions as $idx => $q) {
            $isCorrect = in_array($idx, $correctIndices, true);
            QuizAnswer::create([
                'attempt_id' => $postAttempt->id,
                'question_id' => $q->id,
                'selected_option' => $isCorrect ? 'A' : 'B',
                'is_correct' => $isCorrect,
            ]);
        }

        MockBoardAttempt::create([
            'user_id' => $student->id,
            'mock_board_id' => $board->id,
            'phase_type' => 'pre_boards',
            'quiz_attempt_id' => $postAttempt->id,
            'score' => 3,
            'total' => 10,
            'percentage' => 30.0,
            'passed' => false,
        ]);

        $service = app(MockBoardReadinessService::class);
        $report = $service->getOrGenerateReport($board, $student, true);

        $this->assertEquals(30.0, $report->readiness_percentage);
        $this->assertEquals('low', $report->tier);
        $this->assertEquals(45.0, $report->gap_percentage); // 75 - 30
        $this->assertEquals('Auditing and Assurance', $report->domain_breakdown['weakest_domain']);
        $this->assertContains('Auditing and Assurance', $report->ai_action_plan['priority_domains']);
    }
}
