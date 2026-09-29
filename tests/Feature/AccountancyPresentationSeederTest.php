<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\MockBoard;
use App\Models\MockBoardAttempt;
use App\Models\MockBoardPhase;
use App\Models\MockBoardStatistic;
use App\Models\Module;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Database\Seeders\AccountancyPresentationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountancyPresentationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountancy_presentation_seeder_populates_all_required_data(): void
    {
        $this->seed(AccountancyPresentationSeeder::class);

        // 1. Verify Teacher Account
        $teacher = User::where('idnumber', '23-9999')->first();
        $this->assertNotNull($teacher, 'Teacher 23-9999 must exist.');
        $this->assertEquals('teacher', $teacher->role);
        $this->assertEquals('accountancy', $teacher->program);
        $this->assertEquals('approved', $teacher->status);
        $this->assertTrue(Hash::check('teacher123', $teacher->password));

        // 2. Verify 10 Student Accounts
        $expectedStudentIds = [
            '23-9991', '23-9992', '23-9993', '23-9994', '23-9995',
            '23-9996', '23-9997', '23-9998', '23-10000', '23-10001',
        ];

        $students = User::whereIn('idnumber', $expectedStudentIds)->get();
        $this->assertCount(10, $students, 'There must be exactly 10 seeded students.');

        foreach ($students as $student) {
            $this->assertEquals('student', $student->role);
            $this->assertEquals('accountancy', $student->program);
            $this->assertEquals('approved', $student->status);
            $this->assertTrue(Hash::check('student123', $student->password));
        }

        // 3. Verify Review Class
        $class = ClassModel::where('code', 'ACC401-2026')->first();
        $this->assertNotNull($class, 'Accountancy review class must exist.');
        $this->assertEquals($teacher->id, $class->created_by);
        $this->assertEquals('accountancy', $class->program);
        $this->assertEquals(10, $class->students()->count(), 'All 10 students must be enrolled in the class.');

        // 4. Verify 3 Class Modules
        $modules = Module::where('class_id', $class->id)->where('is_mock_board', false)->get();
        $this->assertCount(3, $modules, 'Class must contain 3 standard modules (Pre-Test, Post-Test, Formal Assessment).');

        $preTestModule = $modules->firstWhere('title', 'FAR Pre-Assessment: Conceptual Framework & Assets');
        $postTestModule = $modules->firstWhere('title', 'FAR Post-Assessment: Liabilities & Advanced Topics');
        $formalAssessmentModule = $modules->firstWhere('title', 'Auditing Practice & Assurance - Midterm Assessment');

        $this->assertNotNull($preTestModule);
        $this->assertNotNull($postTestModule);
        $this->assertNotNull($formalAssessmentModule);

        $this->assertEquals(10, $preTestModule->quizQuestions()->count());
        $this->assertEquals(10, $postTestModule->quizQuestions()->count());
        $this->assertEquals(10, $formalAssessmentModule->quizQuestions()->count());

        // 5. Verify Mock Board Exam
        $mockBoard = MockBoard::where('program', 'accountancy')->first();
        $this->assertNotNull($mockBoard, 'Accountancy mock board must exist.');
        $this->assertEquals($teacher->id, $mockBoard->teacher_id);
        $this->assertEquals('approved', $mockBoard->status);

        $preTestPhase = MockBoardPhase::where('mock_board_id', $mockBoard->id)->where('phase_type', 'pre_test')->first();
        $preBoardsPhase = MockBoardPhase::where('mock_board_id', $mockBoard->id)->where('phase_type', 'pre_boards')->first();

        $this->assertNotNull($preTestPhase);
        $this->assertNotNull($preBoardsPhase);

        $this->assertEquals(10, $preTestPhase->module->quizQuestions()->count());
        $this->assertEquals(10, $preBoardsPhase->module->quizQuestions()->count());

        // 6. Verify Completed Attempts and Answers for All 10 Students
        foreach ($students as $student) {
            // Check Class Pre-Test attempt
            $classPreAttempt = QuizAttempt::where('user_id', $student->id)
                ->where('module_id', $preTestModule->id)
                ->first();
            $this->assertNotNull($classPreAttempt);
            $this->assertEquals('completed', $classPreAttempt->status);
            $this->assertEquals(10, QuizAnswer::where('attempt_id', $classPreAttempt->id)->count());

            // Check Class Post-Test attempt
            $classPostAttempt = QuizAttempt::where('user_id', $student->id)
                ->where('module_id', $postTestModule->id)
                ->first();
            $this->assertNotNull($classPostAttempt);
            $this->assertEquals('completed', $classPostAttempt->status);
            $this->assertEquals(10, QuizAnswer::where('attempt_id', $classPostAttempt->id)->count());

            // Check Formal Assessment attempt
            $formalAttempt = QuizAttempt::where('user_id', $student->id)
                ->where('module_id', $formalAssessmentModule->id)
                ->first();
            $this->assertNotNull($formalAttempt);
            $this->assertEquals('completed', $formalAttempt->status);
            $this->assertEquals(10, QuizAnswer::where('attempt_id', $formalAttempt->id)->count());

            // Check Mock Board Pre-Test phase attempt
            $mbPreAttempt = MockBoardAttempt::where('user_id', $student->id)
                ->where('mock_board_id', $mockBoard->id)
                ->where('mock_board_phase_id', $preTestPhase->id)
                ->first();
            $this->assertNotNull($mbPreAttempt);

            // Check Mock Board Pre-Boards phase attempt
            $mbPostAttempt = MockBoardAttempt::where('user_id', $student->id)
                ->where('mock_board_id', $mockBoard->id)
                ->where('mock_board_phase_id', $preBoardsPhase->id)
                ->first();
            $this->assertNotNull($mbPostAttempt);
        }

        // 7. Verify ANOVA and Mock Board Statistics
        $statistics = MockBoardStatistic::where('mock_board_id', $mockBoard->id)->first();
        $this->assertNotNull($statistics, 'Mock board statistics must be generated.');
        $this->assertEquals(10, $statistics->pre_test_count);
        $this->assertEquals(10, $statistics->pre_boards_count);
        $this->assertGreaterThan(0, $statistics->pre_test_mean);
        $this->assertGreaterThan(0, $statistics->pre_boards_mean);
        $this->assertGreaterThan($statistics->pre_test_mean, $statistics->pre_boards_mean);
    }
}
