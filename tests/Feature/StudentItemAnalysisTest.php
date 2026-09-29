<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\Module;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentItemAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_fetch_item_analysis_for_formal_assessment(): void
    {
        $teacher = $this->createUser(['role' => 'teacher', 'program' => 'teacher']);
        $student = $this->createUser(['role' => 'student', 'program' => 'educ']);

        $class = ClassModel::query()->create([
            'name' => 'Review Class',
            'code' => 'REV101',
            'school_year' => now()->year,
            'created_by' => $teacher->id,
        ]);
        $class->users()->attach($student->id);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'Formal Assessment 1',
            'is_quiz' => true,
            'is_formal_assessment' => true,
            'is_active' => true,
            'passing_grade' => 70,
            'created_by' => $teacher->id,
        ]);

        $q1 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'What is 2 + 2?',
            'options' => ['A' => '3', 'B' => '4', 'C' => '5'],
            'correct_option' => 'B',
            'explanation' => 'Basic arithmetic 2+2=4',
            'points' => 1,
            'order' => 1,
        ]);

        $q2 = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'question_text' => 'Capital of Philippines?',
            'options' => ['A' => 'Manila', 'B' => 'Cebu', 'C' => 'Davao'],
            'correct_option' => 'A',
            'explanation' => 'Manila is the capital city.',
            'points' => 1,
            'order' => 2,
        ]);

        $attempt = QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'attempt_count' => 1,
            'score' => 1,
            'total' => 2,
            'percentage' => 50,
            'passed' => false,
            'status' => 'completed',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);

        // Student answered Q1 correctly (B) and Q2 incorrectly (C)
        QuizAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $q1->id,
            'selected_option' => 'B',
            'is_correct' => true,
        ]);

        QuizAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $q2->id,
            'selected_option' => 'C',
            'is_correct' => false,
        ]);

        $response = $this->actingAs($student)->getJson(route('quiz.analysis', $module));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'score' => 1,
            'total' => 2,
            'summary' => [
                'total' => 2,
                'correct' => 1,
                'incorrect' => 1,
            ],
        ]);

        $questions = $response->json('questions');
        $this->assertCount(2, $questions);

        // Check Q1 details
        $this->assertSame('B', $questions[0]['selected_option']);
        $this->assertSame('B', $questions[0]['correct_option']);
        $this->assertTrue($questions[0]['is_correct']);
        $this->assertSame('Basic arithmetic 2+2=4', $questions[0]['explanation']);

        // Check Q2 details
        $this->assertSame('C', $questions[1]['selected_option']);
        $this->assertSame('A', $questions[1]['correct_option']);
        $this->assertFalse($questions[1]['is_correct']);
        $this->assertSame('Manila is the capital city.', $questions[1]['explanation']);
    }

    public function test_student_can_fetch_item_analysis_for_lecture_stage_quiz(): void
    {
        $teacher = $this->createUser(['role' => 'teacher', 'program' => 'teacher']);
        $student = $this->createUser(['role' => 'student', 'program' => 'educ']);

        $class = ClassModel::query()->create([
            'name' => 'Review Class',
            'code' => 'REV102',
            'school_year' => now()->year,
            'created_by' => $teacher->id,
        ]);
        $class->users()->attach($student->id);

        $module = Module::query()->create([
            'class_id' => $class->id,
            'title' => 'Lecture 1 with Pre/Post Test',
            'is_quiz' => true,
            'is_lecture' => true,
            'is_active' => true,
            'passing_grade' => 60,
            'created_by' => $teacher->id,
        ]);

        $qPre = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'quiz_stage' => 'pre_test',
            'question_text' => 'Pre-test Question 1',
            'options' => ['A' => 'Opt 1', 'B' => 'Opt 2'],
            'correct_option' => 'A',
            'points' => 1,
            'order' => 1,
        ]);

        $attempt = QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'quiz_stage' => 'pre_test',
            'attempt_count' => 1,
            'score' => 1,
            'total' => 1,
            'percentage' => 100,
            'passed' => true,
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        QuizAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $qPre->id,
            'selected_option' => 'A',
            'is_correct' => true,
        ]);

        // 1. Student is forbidden from fetching pre-test item analysis
        $response = $this->actingAs($student)->getJson(route('quiz.analysis', [
            'module' => $module,
            'quiz_stage' => 'pre_test',
        ]));

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'disabled' => true,
        ]);

        // 2. Student CAN fetch post-test item analysis
        $postAttempt = QuizAttempt::query()->create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'quiz_stage' => 'post_test',
            'attempt_count' => 1,
            'score' => 1,
            'total' => 1,
            'percentage' => 100,
            'passed' => true,
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $qPost = QuizQuestion::query()->create([
            'module_id' => $module->id,
            'quiz_stage' => 'post_test',
            'question_text' => 'Post-test Question 1',
            'options' => ['A' => 'Opt 1', 'B' => 'Opt 2'],
            'correct_option' => 'A',
            'points' => 1,
            'order' => 1,
        ]);

        QuizAnswer::query()->create([
            'attempt_id' => $postAttempt->id,
            'question_id' => $qPost->id,
            'selected_option' => 'A',
            'is_correct' => true,
        ]);

        $postResponse = $this->actingAs($student)->getJson(route('quiz.analysis', [
            'module' => $module,
            'quiz_stage' => 'post_test',
        ]));

        $postResponse->assertOk();
        $postResponse->assertJson([
            'success' => true,
            'score' => 1,
            'total' => 1,
        ]);
    }

    private function createUser(array $overrides = []): User
    {
        static $counter = 7000;
        $counter++;

        return User::query()->create(array_merge([
            'idnumber' => 'IA'.$counter,
            'name' => 'IA User '.$counter,
            'email' => 'iauser'.$counter.'@example.test',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'status' => 'active',
            'program' => 'educ',
            'program_locked' => false,
            'email_verified_at' => now(),
        ], $overrides));
    }
}
