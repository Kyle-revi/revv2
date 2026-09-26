<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\Module;
use App\Models\ModuleSubpart;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormalAssessmentLectureLockTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private ClassModel $class;

    private Module $formalAssessmentModule;

    private Module $lectureModule;

    private ModuleSubpart $lectureSubpart;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->teacher = User::factory()->create([
            'role' => 'teacher',
            'program' => 'education',
        ]);

        $this->student = User::factory()->create([
            'role' => 'student',
            'program' => 'education',
        ]);

        $this->class = ClassModel::factory()->create([
            'created_by' => $this->teacher->id,
            'program' => 'education',
        ]);
        $this->class->users()->attach($this->student->id);

        $this->formalAssessmentModule = Module::factory()->create([
            'class_id' => $this->class->id,
            'title' => 'Midterm Exam Formal Assessment',
            'is_formal_assessment' => true,
            'is_quiz' => true,
            'quiz_stage' => 'pre_test',
        ]);

        // Create dummy lecture file
        $lectureFilePath = 'modules/sample_lecture.pdf';
        Storage::disk('public')->put($lectureFilePath, '%PDF-1.4 dummy content');

        $this->lectureModule = Module::factory()->create([
            'class_id' => $this->class->id,
            'title' => 'Chapter 1 Lecture',
            'is_formal_assessment' => false,
            'is_quiz' => false,
            'is_lecture' => true,
            'file_path' => $lectureFilePath,
            'file_type' => 'pdf',
        ]);

        $this->lectureSubpart = ModuleSubpart::create([
            'module_id' => $this->lectureModule->id,
            'title' => 'Subpart 1 - Introduction',
            'description' => 'Intro lecture details',
            'order' => 1,
        ]);
    }

    public function test_lecture_modules_and_files_are_locked_when_formal_assessment_is_in_progress(): void
    {
        // Student has an active, in-progress formal assessment
        QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $this->formalAssessmentModule->id,
            'quiz_stage' => 'pre_test',
            'attempt_count' => 1,
            'score' => 0,
            'total' => 10,
            'percentage' => 0,
            'passed' => false,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student);

        // 1. Check student modules page
        $response = $this->get(route('student.modules', $this->class));
        $response->assertStatus(200);
        $response->assertViewHas('hasActiveAssessment', true);
        $response->assertSee('Formal Assessment in Progress:');
        $response->assertSee('In Assessment');

        // 2. Check direct lecture file viewing is blocked with 403
        $fileResponse = $this->get(route('module.view', $this->lectureModule));
        $fileResponse->assertStatus(403);

        // 3. Check pdfjs viewer is blocked with 403
        $pdfjsResponse = $this->get(route('module.pdfjs', $this->lectureModule));
        $pdfjsResponse->assertStatus(403);

        // 4. Check subparts listing is blocked with 403
        $subpartsResponse = $this->get(route('module.subparts.student.index', $this->lectureModule));
        $subpartsResponse->assertStatus(403);
    }

    public function test_lecture_modules_unlock_when_formal_assessment_is_completed(): void
    {
        // Student has finished and completed their formal assessment
        QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $this->formalAssessmentModule->id,
            'quiz_stage' => 'pre_test',
            'attempt_count' => 1,
            'score' => 8,
            'total' => 10,
            'percentage' => 80,
            'passed' => true,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $this->actingAs($this->student);

        // 1. Check student modules page shows no active assessment lock
        $response = $this->get(route('student.modules', $this->class));
        $response->assertStatus(200);
        $response->assertViewHas('hasActiveAssessment', false);
        $response->assertDontSee('Formal Assessment in Progress:');

        // 2. Check subparts are accessible
        $subpartsResponse = $this->get(route('module.subparts.student.index', $this->lectureModule));
        $subpartsResponse->assertStatus(200);
    }

    public function test_practice_quizzes_do_not_lock_lecture_modules(): void
    {
        $practiceModule = Module::factory()->create([
            'class_id' => $this->class->id,
            'title' => 'Practice Quiz Chapter 1',
            'is_formal_assessment' => false,
            'is_quiz' => true,
            'quiz_stage' => null,
        ]);

        // Student is taking a practice quiz (not a formal assessment)
        QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $practiceModule->id,
            'quiz_stage' => null,
            'attempt_count' => 1,
            'score' => 0,
            'total' => 5,
            'percentage' => 0,
            'passed' => false,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student);

        $response = $this->get(route('student.modules', $this->class));
        $response->assertStatus(200);
        $response->assertViewHas('hasActiveAssessment', false);

        // Subparts still accessible
        $subpartsResponse = $this->get(route('module.subparts.student.index', $this->lectureModule));
        $subpartsResponse->assertStatus(200);
    }

    public function test_pre_test_in_progress_shows_pre_test_banner(): void
    {
        $preTestModule = Module::factory()->create([
            'class_id' => $this->class->id,
            'title' => 'Biology Unit 1 Pre-Test',
            'is_formal_assessment' => false,
            'is_quiz' => true,
            'quiz_stage' => 'pre_test',
        ]);

        QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $preTestModule->id,
            'quiz_stage' => 'pre_test',
            'attempt_count' => 1,
            'score' => 0,
            'total' => 5,
            'percentage' => 0,
            'passed' => false,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student);

        $response = $this->get(route('student.modules', $this->class));
        $response->assertStatus(200);
        $response->assertViewHas('hasActiveAssessment', true);
        $response->assertSee('Pre-Test in Progress:');
    }

    public function test_post_test_in_progress_shows_post_test_banner(): void
    {
        $postTestModule = Module::factory()->create([
            'class_id' => $this->class->id,
            'title' => 'Biology Unit 1 Post-Test',
            'is_formal_assessment' => false,
            'is_quiz' => true,
            'quiz_stage' => 'post_test',
        ]);

        QuizAttempt::create([
            'user_id' => $this->student->id,
            'module_id' => $postTestModule->id,
            'quiz_stage' => 'post_test',
            'attempt_count' => 1,
            'score' => 0,
            'total' => 5,
            'percentage' => 0,
            'passed' => false,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student);

        $response = $this->get(route('student.modules', $this->class));
        $response->assertStatus(200);
        $response->assertViewHas('hasActiveAssessment', true);
        $response->assertSee('Post-Test in Progress:');
    }

    public function test_quiz_questions_endpoint_returns_questions_for_pre_and_post_tests_with_null_quiz_stage(): void
    {
        $preTestModule = Module::factory()->create([
            'class_id' => $this->class->id,
            'title' => 'Chemistry Pre-Test',
            'is_formal_assessment' => false,
            'is_quiz' => true,
            'quiz_stage' => 'pre_test',
        ]);

        // Questions created without quiz_stage (null)
        QuizQuestion::create([
            'module_id' => $preTestModule->id,
            'question_text' => 'What is H2O?',
            'options' => json_encode(['A' => 'Water', 'B' => 'Air', 'C' => 'Fire', 'D' => 'Earth']),
            'correct_option' => 'A',
            'points' => 1,
            'order' => 1,
            'quiz_stage' => null,
        ]);

        $this->actingAs($this->student);

        // Fetch questions requesting stage pre_test
        $response = $this->getJson(route('quiz.get.questions', [$preTestModule, 'quiz_stage' => 'pre_test']));
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('questions'));
        $this->assertSame('What is H2O?', $response->json('questions.0.question_text'));
        $this->assertIsArray($response->json('questions.0.options'));
    }
}
