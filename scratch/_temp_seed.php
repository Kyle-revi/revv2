<?php
use AppModelsUser;
use AppModelsClassModel;
use AppModelsModule;
use AppModelsQuizQuestion;
use IlluminateSupportFacadesHash;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(IlluminateContractsConsoleKernel::class);
$kernel->bootstrap();

User::create([
    'name' => 'E2E Admin',
    'email' => 'admin.test@example.com',
    'idnumber' => 'ADM-2026-001',
    'password' => Hash::make('password'),
    'role' => 'admin',
    'status' => 'approved',
]);

$teacher = User::create([
    'name' => 'E2E Teacher',
    'email' => 'teacher.test@example.com',
    'idnumber' => 'TCH-2026-001',
    'password' => Hash::make('password'),
    'role' => 'teacher',
    'program' => 'psych',
    'status' => 'approved',
]);

$student = User::create([
    'name' => 'E2E Student',
    'email' => 'student.test@example.com',
    'idnumber' => 'STU-2026-999',
    'password' => Hash::make('password'),
    'role' => 'student',
    'program' => 'psych',
    'status' => 'approved',
]);

$cls = ClassModel::create([
    'name' => 'E2E Psychology 101',
    'created_by' => $teacher->id,
    'program' => 'psych',
    'code' => 'PSY101',
    'school_year' => 2026,
    'year_level' => '1st Year',
]);

$cls->users()->attach($student->id);

$mod = Module::create([
    'title' => 'E2E Psychology Pre-Test',
    'class_id' => $cls->id,
    'is_formal_assessment' => 1,
    'quiz_stage' => 'pre_test',
    'assessment_purpose' => 'pre_test',
    'visibility' => 'all',
    'is_active' => 1,
    'passing_grade' => 75,
    'max_attempts' => 2,
]);

QuizQuestion::create([
    'module_id' => $mod->id,
    'question_text' => 'What is the primary focus of psychology?',
    'quiz_stage' => 'pre_test',
    'options' => ['Behavior and Mind', 'Atmosphere and Clouds', 'Quantum Physics', 'Ancient Ruins'],
    'correct_option' => 'Behavior and Mind',
    'points' => 1,
    'order' => 1,
]);

Module::create([
    'title' => 'E2E Lecture 1: Cognitive Basics',
    'class_id' => $cls->id,
    'is_lecture' => 1,
    'is_formal_assessment' => 0,
    'visibility' => 'all',
    'is_active' => 1,
]);

echo "SUCCESS_SEEDED\n";
