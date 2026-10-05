<?php

namespace Tests\Feature;

use App\Models\AssessmentAttemptGrant;
use App\Models\ClassModel;
use App\Models\Module;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentAssessmentAttemptsTest extends TestCase
{
    use RefreshDatabase;

    private static int $userSeq = 1000;

    private static int $classSeq = 100;

    private static int $moduleSeq = 100;

    private function createStudent(array $overrides = []): User
    {
        $n = ++self::$userSeq;

        return User::query()->create(array_merge([
            'idnumber' => 'STU'.$n,
            'name' => 'Student '.$n,
            'email' => 'student'.$n.'@example.test',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'program' => 'educ',
            'status' => 'active',
            'program_locked' => false,
        ], $overrides));
    }

    private function createTeacher(array $overrides = []): User
    {
        $n = ++self::$userSeq;

        return User::query()->create(array_merge([
            'idnumber' => 'TCH'.$n,
            'name' => 'Teacher '.$n,
            'email' => 'teacher'.$n.'@example.test',
            'password' => Hash::make('password123'),
            'role' => 'teacher',
            'program' => 'educ',
            'status' => 'active',
            'program_locked' => false,
        ], $overrides));
    }

    private function createClass(User $teacher, array $overrides = []): ClassModel
    {
        $n = ++self::$classSeq;

        return ClassModel::query()->create(array_merge([
            'name' => 'Class '.$n,
            'code' => 'CLS'.$n,
            'created_by' => $teacher->id,
        ], $overrides));
    }

    private function createAssessment(ClassModel $class, array $overrides = []): Module
    {
        $n = ++self::$moduleSeq;

        return Module::query()->create(array_merge([
            'class_id' => $class->id,
            'title' => 'Assessment '.$n,
            'description' => 'Test assessment',
            'is_formal_assessment' => true,
            'is_active' => true,
            'passing_grade' => 75,
            'time_limit' => 60,
            'max_attempts' => 3,
            'created_by' => $class->teacher_id,
        ], $overrides));
    }

    private function createQuestion(Module $module, array $overrides = []): QuizQuestion
    {
        return QuizQuestion::query()->create(array_merge([
            'module_id' => $module->id,
            'question_text' => 'Sample question?',
            'options' => json_encode(['A' => 'Option A', 'B' => 'Option B', 'C' => 'Option C', 'D' => 'Option D']),
            'correct_option' => 'A',
            'points' => 1,
            'order' => 1,
        ], $overrides));
    }

    // ── Tests ───────────────────────────────────────────────────────

    public function test_student_can_see_attempt_limits_on_assessment_list()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class, ['max_attempts' => 3]);

        // Enroll student
        $class->users()->attach($student);

        // Login and view assessment list
        $response = $this->actingAs($student)->get(route('assessment'));

        $response->assertStatus(200);
        $response->assertSee($assessment->title);
        $response->assertSee('0 / 3'); // 0 attempts used out of 3 allowed
    }

    public function test_student_can_see_used_attempts_on_assessment_list()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class, ['max_attempts' => 3]);

        // Enroll student
        $class->users()->attach($student);

        // Create an attempt (simulate 1 attempt used)
        QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $assessment->id,
            'attempt_count' => 1,
            'percentage' => 80,
            'score' => 80,
            'total' => 100,
            'status' => 'completed',
        ]);

        // Login and view assessment list
        $response = $this->actingAs($student)->get(route('assessment'));

        $response->assertStatus(200);
        $response->assertSee('1 / 3'); // 1 attempt used out of 3 allowed
    }

    public function test_student_sees_take_assessment_button_when_no_attempt()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class);

        // Enroll student
        $class->users()->attach($student);

        // Login and view assessment list
        $response = $this->actingAs($student)->get(route('assessment'));

        $response->assertStatus(200);
        $response->assertSee('Take Assessment');
        $response->assertDontSee('Attempts Used Up');
        $response->assertDontSee('Retake');
    }

    public function test_student_sees_retake_button_when_attempts_remaining()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class, ['max_attempts' => 3]);

        // Enroll student
        $class->users()->attach($student);

        // Create an attempt
        QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $assessment->id,
            'attempt_count' => 1,
            'percentage' => 50,
            'score' => 50,
            'total' => 100,
            'status' => 'completed',
        ]);

        // Login and view assessment list
        $response = $this->actingAs($student)->get(route('assessment'));

        $response->assertStatus(200);
        $response->assertSee('Retake');
        $response->assertDontSee('Attempts Used Up');
        $response->assertDontSee('Take Assessment');
    }

    public function test_student_sees_attempts_used_up_when_all_attempts_exhausted()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class, ['max_attempts' => 1]);

        // Enroll student
        $class->users()->attach($student);

        // Create an attempt
        QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $assessment->id,
            'attempt_count' => 1,
            'percentage' => 50,
            'score' => 50,
            'total' => 100,
            'status' => 'completed',
        ]);

        // Login and view assessment list
        $response = $this->actingAs($student)->get(route('assessment'));

        $response->assertStatus(200);
        $response->assertSee('Attempts Used Up');
        $response->assertDontSee('Retake');
        $response->assertDontSee('Take Assessment');
    }

    public function test_student_sees_attempt_info_on_take_screen()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class);

        // Enroll student
        $class->users()->attach($student);

        // Create a question
        $this->createQuestion($assessment);

        // Login and view take screen
        $response = $this->actingAs($student)->get(route('assessment.take', $assessment));

        $response->assertStatus(200);
        $response->assertSee('Attempt 1 of 3'); // First attempt out of 3 allowed
    }

    public function test_student_sees_correct_attempt_number_on_subsequent_attempt()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class, ['max_attempts' => 3]);

        // Enroll student
        $class->users()->attach($student);

        // Create a question
        $this->createQuestion($assessment);

        // Create an attempt
        QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $assessment->id,
            'attempt_count' => 1,
            'percentage' => 50,
            'score' => 50,
            'total' => 100,
            'status' => 'completed',
        ]);

        // Login and view take screen
        $response = $this->actingAs($student)->get(route('assessment.take', $assessment));

        $response->assertStatus(200);
        $response->assertSee('Attempt 2 of 3'); // Second attempt out of 3 allowed
    }

    public function test_module_allows_attempts_for_method_calculates_correctly()
    {
        $teacher = $this->createTeacher();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class, ['max_attempts' => 2]);

        $student = $this->createStudent();
        $class->users()->attach($student);

        // Without grants, should return max_attempts
        $this->assertEquals(2, $assessment->allowedAttemptsFor($student->id));
    }

    public function test_student_sees_resume_button_for_an_in_progress_attempt()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class);
        $class->users()->attach($student);
        $this->createQuestion($assessment);

        QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $assessment->id,
            'attempt_count' => 1,
            'percentage' => 0,
            'score' => 0,
            'total' => 0,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($student)->get(route('assessment'));

        $response->assertOk();
        $response->assertSee('Resume Assessment');
        $response->assertDontSee('Retake (2 of 3)');
    }

    public function test_extra_attempt_grant_updates_the_student_limit_and_allows_a_retake()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class, ['max_attempts' => 2]);
        $class->users()->attach($student);
        $this->createQuestion($assessment);

        $attempt = QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $assessment->id,
            'attempt_count' => 2,
            'percentage' => 50,
            'score' => 1,
            'total' => 2,
            'status' => 'completed',
        ]);

        AssessmentAttemptGrant::query()->create([
            'module_id' => $assessment->id,
            'user_id' => $student->id,
            'granted_by' => $teacher->id,
            'extra_attempts' => 1,
        ]);

        $response = $this->actingAs($student)->get(route('assessment'));

        $response->assertOk();
        $response->assertSee('2 / 3');
        $response->assertSee('Retake (3 of 3)');

        $this->actingAs($student)
            ->postJson(route('quiz.start', $assessment))
            ->assertOk()
            ->assertJsonPath('attempt_count', 3);

        $this->assertSame('in_progress', $attempt->refresh()->status);
    }

    public function test_teacher_can_persist_a_base_attempt_limit()
    {
        $teacher = $this->createTeacher();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class, ['max_attempts' => 1]);

        $this->actingAs($teacher)
            ->putJson(route('quiz.max-attempts.update', $assessment), ['max_attempts' => 3])
            ->assertOk()
            ->assertJsonPath('max_attempts', 3);

        $this->assertSame(3, $assessment->refresh()->max_attempts);
    }

    public function test_teacher_can_incrementally_grant_extra_attempts()
    {
        $teacher = $this->createTeacher();
        $student = $this->createStudent();
        $class = $this->createClass($teacher);
        $assessment = $this->createAssessment($class, ['max_attempts' => 1]);
        $class->users()->attach($student);

        // First grant: +1
        $this->actingAs($teacher)
            ->postJson(route('quiz.grant.attempt', [$assessment, $student]), [
                'extra_attempts' => 1,
                'reason' => 'First retry grant',
            ])
            ->assertOk()
            ->assertJsonPath('extra_granted', 1)
            ->assertJsonPath('total_allowed', 2);

        $this->assertEquals(2, $assessment->allowedAttemptsFor($student->id));

        // Second grant: +2 more (cumulative should be 1 + 2 = 3)
        $this->actingAs($teacher)
            ->postJson(route('quiz.grant.attempt', [$assessment, $student]), [
                'extra_attempts' => 2,
                'reason' => 'Second retry grant',
            ])
            ->assertOk()
            ->assertJsonPath('extra_granted', 3)
            ->assertJsonPath('total_allowed', 4);

        $this->assertEquals(4, $assessment->allowedAttemptsFor($student->id));
    }
}
