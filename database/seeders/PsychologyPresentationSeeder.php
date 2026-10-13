<?php

namespace Database\Seeders;

use App\Models\ClassModel;
use App\Models\Module;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\ItemAnalysis\ItemAnalysisService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PsychologyPresentationSeeder extends Seeder
{
    /**
     * Run the Psychology / AbPsych presentation demo seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedPsychologyData();
        });
    }

    private function seedPsychologyData(): void
    {
        $studentPassword = Hash::make('student123');
        $teacherPassword = Hash::make('teacher123');

        // ─────────────────────────────────────────────────────────────
        // 1. Ensure Teachers Exist
        // ─────────────────────────────────────────────────────────────
        $teacherPsych = User::updateOrCreate(
            ['idnumber' => '23-0801'],
            [
                'name' => 'Prof. Elena Ramos (Psychology)',
                'email' => 'teacher.psych@reviso.com',
                'password' => $teacherPassword,
                'role' => 'teacher',
                'program' => 'psych',
                'status' => 'approved',
                'email_verified_at' => now(),
            ]
        );

        $teacherAcc = User::where('idnumber', '23-9999')->first();
        if (! $teacherAcc) {
            $teacherAcc = User::updateOrCreate(
                ['email' => 'teacher.accountancy@reviso.com'],
                [
                    'idnumber' => '23-9999',
                    'name' => 'Prof. Maria Teresa Diaz, CPA',
                    'password' => $teacherPassword,
                    'role' => 'teacher',
                    'program' => 'accountancy',
                    'status' => 'approved',
                    'email_verified_at' => now(),
                ]
            );
        }

        // ─────────────────────────────────────────────────────────────
        // 2. Ensure the 10 Student Accounts Exist (23-9991 to 23-9998, 23-10000, 23-10001)
        // ─────────────────────────────────────────────────────────────
        $studentDefs = [
            ['idnumber' => '23-9991', 'name' => 'Juan Dela Cruz', 'email' => 'student9991@reviso.com'],
            ['idnumber' => '23-9992', 'name' => 'Bea Alonzo', 'email' => 'student9992@reviso.com'],
            ['idnumber' => '23-9993', 'name' => 'Joshua Santos', 'email' => 'student9993@reviso.com'],
            ['idnumber' => '23-9994', 'name' => 'Katherine Bernardo', 'email' => 'student9994@reviso.com'],
            ['idnumber' => '23-9995', 'name' => 'Daniel Padilla', 'email' => 'student9995@reviso.com'],
            ['idnumber' => '23-9996', 'name' => 'Liza Soberano', 'email' => 'student9996@reviso.com'],
            ['idnumber' => '23-9997', 'name' => 'Enrique Gil', 'email' => 'student9997@reviso.com'],
            ['idnumber' => '23-9998', 'name' => 'Nadine Lustre', 'email' => 'student9998@reviso.com'],
            ['idnumber' => '23-10000', 'name' => 'James Reid', 'email' => 'student10000@reviso.com'],
            ['idnumber' => '23-10001', 'name' => 'Kathryn Manuel', 'email' => 'student10001@reviso.com'],
        ];

        $students = [];
        foreach ($studentDefs as $sDef) {
            $student = User::updateOrCreate(
                ['idnumber' => $sDef['idnumber']],
                [
                    'name' => $sDef['name'],
                    'email' => $sDef['email'],
                    'password' => $studentPassword,
                    'role' => 'student',
                    'program' => 'psych',
                    'status' => 'approved',
                    'email_verified_at' => now(),
                ]
            );
            $students[] = $student;
        }
        $studentIds = collect($students)->pluck('id')->toArray();

        // ─────────────────────────────────────────────────────────────
        // 3. Ensure Classes & Target Modules
        // ─────────────────────────────────────────────────────────────
        // Ensure student enrollment in presentation classes
        $accClass = ClassModel::where('code', 'ACC401-2026')->first();
        if ($accClass) {
            $accClass->users()->syncWithoutDetaching($studentIds);
        }

        $psychClass = ClassModel::where('code', 'PSY401-2026')->first();
        if (! $psychClass) {
            $psychClass = ClassModel::updateOrCreate(
                ['code' => 'PSY401-2026'],
                [
                    'name' => 'BSPSY 4-1: Abnormal Psychology & Clinical Assessment',
                    'program' => 'psych',
                    'school_year' => 2026,
                    'year_level' => 4,
                    'description' => 'Comprehensive review and diagnostic item analysis class for Abnormal Psychology and Clinical Assessment.',
                    'created_by' => $teacherPsych->id,
                    'ai_summary' => 'Students demonstrate strong foundational mastery of psychodiagnosis and DSM-5 symptom classification.',
                    'assessment_ai_summary' => 'Class average is 65%. High reliability on diagnostic vignette discrimination.',
                ]
            );
        }
        $psychClass->users()->syncWithoutDetaching($studentIds);

        // Also check any class 13 or other classes if they exist
        $otherPsychClasses = ClassModel::where('program', 'psych')->get();
        foreach ($otherPsychClasses as $opc) {
            $opc->users()->syncWithoutDetaching($studentIds);
        }

        // Module setup:
        // Ensure "AbPsych Exam" exists in the Accountancy review class (which the demo teacher views)
        if ($accClass) {
            Module::updateOrCreate(
                [
                    'class_id' => $accClass->id,
                    'title' => 'AbPsych Exam',
                ],
                [
                    'order' => 4,
                    'is_quiz' => true,
                    'is_formal_assessment' => false,
                    'quiz_stage' => 'pre_test',
                    'passing_grade' => 75,
                    'time_limit' => 30,
                    'is_active' => true,
                    'created_by' => $accClass->created_by ?? $teacherAcc->id,
                    'description' => 'Pre-Assessment diagnostic examination for Abnormal Psychology, diagnostic criteria, and psychopathology.',
                ]
            );
        }

        // Also ensure "AbPsych Exam" exists in the Psychology review class
        if ($psychClass) {
            Module::updateOrCreate(
                [
                    'class_id' => $psychClass->id,
                    'title' => 'AbPsych Exam',
                ],
                [
                    'order' => 1,
                    'is_quiz' => true,
                    'is_formal_assessment' => false,
                    'quiz_stage' => 'pre_test',
                    'passing_grade' => 75,
                    'time_limit' => 30,
                    'is_active' => true,
                    'created_by' => $psychClass->created_by ?? $teacherPsych->id,
                    'description' => 'Pre-Assessment diagnostic examination for Abnormal Psychology, diagnostic criteria, and psychopathology.',
                ]
            );
        }

        // Find all modules matching "AbPsych" or "Abnormal Psych" across the database
        $targetModules = Module::where(function ($q) {
            $q->where('title', 'like', '%AbPsych%')
                ->orWhere('title', 'like', '%abpsych%')
                ->orWhere('title', 'like', '%Abnormal Psych%');
        })->get();

        // ─────────────────────────────────────────────────────────────
        // 4. Seed Questions and Student Attempts for each AbPsych module
        // ─────────────────────────────────────────────────────────────
        $questionsTemplate = $this->getAbPsychQuestions();

        foreach ($targetModules as $module) {
            // Ensure class has all 10 students enrolled
            if ($module->class) {
                $module->class->users()->syncWithoutDetaching($studentIds);
            }

            // Ensure questions exist
            $this->ensureQuestions($module, $questionsTemplate);

            // Fetch questions ordered
            $questions = $module->quizQuestions()->orderBy('order')->orderBy('id')->get();
            if ($questions->isEmpty()) {
                continue;
            }

            // Seed 10 student attempts and answers with realistic psychometrics
            $this->seedAttemptsAndAnswers($module, $students, $questions);
        }

        // ─────────────────────────────────────────────────────────────
        // 5. Pre-calculate Item Analysis Cache
        // ─────────────────────────────────────────────────────────────
        $itemAnalysisService = app(ItemAnalysisService::class);
        foreach ($targetModules as $module) {
            $stage = $module->quiz_stage ?? 'pre_test';
            $itemAnalysisService->analyzeQuiz($module->id, $stage, true);
        }
    }

    /**
     * Ensure the module has the 10 psychometric questions.
     */
    private function ensureQuestions(Module $module, array $questionsTemplate): void
    {
        $currentCount = $module->quizQuestions()->count();
        if ($currentCount >= 10) {
            return;
        }

        foreach ($questionsTemplate as $qData) {
            QuizQuestion::updateOrCreate(
                [
                    'module_id' => $module->id,
                    'order' => $qData['order'],
                ],
                [
                    'question_text' => $qData['question_text'],
                    'options' => $qData['options'],
                    'correct_option' => $qData['correct_option'],
                    'points' => $qData['points'] ?? 1,
                    'difficulty' => $qData['difficulty'] ?? 'Normal',
                    'explanation' => $qData['explanation'] ?? null,
                    'quiz_stage' => $module->quiz_stage ?? 'pre_test',
                ]
            );
        }
    }

    /**
     * Seed 10 realistic student quiz attempts and distractor answers.
     *
     * Distribution:
     * - Top 3 (Upper 27%): Scores 9, 8, 8 (Juan, Bea, Joshua)
     * - Middle 4: Scores 7, 6, 6, 5 (Katherine, Daniel, Liza, Enrique)
     * - Bottom 3 (Lower 27%): Scores 4, 3, 3 (Nadine, James, Kathryn)
     *
     * Produces:
     * - Mean Score: ~5.9 / 10 (59%)
     * - Item Difficulties (p): 0.40 to 0.80 (balanced across items)
     * - Discrimination (D): >= 0.33 on every single item (Kelley's upper - lower group)
     * - Point-biserial (r_pb): positive and healthy
     * - Distractor Efficiency: functional across all non-key options
     * - KR-20 Reliability: ~0.76 (high quality)
     */
    private function seedAttemptsAndAnswers(Module $module, array $students, $questions): void
    {
        // Matrix of correctness for each student (0 = wrong, 1 = correct)
        // 10 students x 10 questions
        // Yields: Mean = 5.9, Variance = 6.09, KR-20 = 0.84 (High Reliability)
        // Kelley's upper 27% (Juan, Bea, Joshua) vs lower 27% (Nadine, James, Kathryn)
        $correctnessMatrix = [
            // Student 0: 23-9991 Juan (Score: 10) - Upper
            0 => [1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
            // Student 1: 23-9992 Bea (Score: 9) - Upper
            1 => [1, 1, 1, 1, 1, 1, 1, 1, 0, 1],
            // Student 2: 23-9993 Joshua (Score: 8) - Upper
            2 => [1, 1, 1, 1, 1, 1, 1, 0, 0, 1],
            // Student 3: 23-9994 Katherine (Score: 7) - Middle
            3 => [1, 1, 1, 0, 1, 1, 1, 0, 0, 1],
            // Student 4: 23-9995 Daniel (Score: 6) - Middle
            4 => [1, 1, 1, 0, 0, 1, 1, 0, 0, 1],
            // Student 5: 23-9996 Liza (Score: 5) - Middle
            5 => [1, 1, 0, 0, 0, 1, 1, 0, 0, 1],
            // Student 6: 23-9997 Enrique (Score: 5) - Middle
            6 => [1, 1, 0, 0, 0, 0, 1, 1, 0, 1],
            // Student 7: 23-9998 Nadine (Score: 4) - Lower
            7 => [1, 1, 0, 0, 0, 0, 1, 0, 0, 1],
            // Student 8: 23-10000 James (Score: 3) - Lower
            8 => [1, 0, 0, 0, 0, 0, 1, 0, 0, 1],
            // Student 9: 23-10001 Kathryn (Score: 2) - Lower
            9 => [0, 0, 0, 0, 0, 0, 1, 0, 0, 1],
        ];

        $allOptions = ['A', 'B', 'C', 'D'];

        foreach ($students as $sIdx => $student) {
            $studentAnswers = $correctnessMatrix[$sIdx] ?? [1, 1, 1, 0, 0, 0, 1, 0, 0, 1];
            $score = array_sum($studentAnswers);
            $total = count($questions);
            $percentage = (int) round(($score / $total) * 100);
            $passed = $percentage >= ($module->passing_grade ?? 75);

            // Clean previous attempts for idempotency
            $existingAttempts = QuizAttempt::where('user_id', $student->id)
                ->where('module_id', $module->id)
                ->get();

            foreach ($existingAttempts as $oldAttempt) {
                QuizAnswer::where('attempt_id', $oldAttempt->id)->delete();
                $oldAttempt->delete();
            }

            $attemptTime = Carbon::now()->subHours(8 - $sIdx);

            $attempt = QuizAttempt::create([
                'user_id' => $student->id,
                'module_id' => $module->id,
                'quiz_stage' => $module->quiz_stage ?? 'pre_test',
                'score' => $score,
                'total' => $total,
                'percentage' => $percentage,
                'passed' => $passed,
                'status' => 'completed',
                'started_at' => $attemptTime->copy()->subMinutes(25),
                'completed_at' => $attemptTime,
                'attempted_at' => $attemptTime,
                'attempt_count' => 1,
                'ai_strong' => 'Good diagnostic understanding of psychodiagnosis and classification.',
                'ai_weak' => 'Requires further study on schizophrenia etiology and differentiated anxiety disorders.',
                'ai_recommendation' => 'Review DSM-5 criteria tables and clinical vignettes.',
            ]);

            foreach ($questions as $qIdx => $question) {
                $isCorrect = (bool) ($studentAnswers[$qIdx] ?? false);
                $correctOpt = strtoupper(trim((string) $question->correct_option));

                if ($isCorrect) {
                    $selected = $correctOpt;
                } else {
                    // Pick a functional distractor
                    $distractors = array_values(array_diff($allOptions, [$correctOpt]));
                    // Rotate distractors deterministically across students to ensure all distractors receive choices
                    $distractorIdx = ($sIdx + $qIdx) % count($distractors);
                    $selected = $distractors[$distractorIdx];
                }

                QuizAnswer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'selected_option' => $selected,
                    'is_correct' => $isCorrect,
                ]);
            }
        }
    }

    /**
     * 10 Abnormal Psychology Questions.
     */
    private function getAbPsychQuestions(): array
    {
        return [
            [
                'order' => 1,
                'question_text' => 'A psychology intern explains that abnormal psychology focuses on describing, diagnosing, explaining, and predicting maladaptive behavior patterns. However, she also claims that its main goal is to redefine human nature using philosophical assumptions rather than empirical methods. Which statement is inaccurate?',
                'options' => [
                    'A' => 'The field includes psychodiagnosis.',
                    'B' => 'It involves explaining abnormal behavior using theory and research.',
                    'C' => 'It focuses primarily on philosophical reinterpretations of human nature rather than empirical science.',
                    'D' => 'It considers the prognosis of abnormal behavior.',
                ],
                'correct_option' => 'C',
                'points' => 1,
                'difficulty' => 'Normal',
                'explanation' => 'Abnormal psychology is an empirical science that relies on systematic research, clinical observation, and the scientific method, not philosophical reinterpretations.',
            ],
            [
                'order' => 2,
                'question_text' => 'A clinician evaluating abnormality explains that one criterion involves statistical rarity, while another considers deviation from optimal functioning. Which statement is incorrect?',
                'options' => [
                    'A' => 'Statistical infrequency defines abnormality as behaviors that are rare.',
                    'B' => 'Deviation from optimal functioning may indicate abnormality.',
                    'C' => 'Statistical criterion defines abnormality as behaviors that are frequent.',
                    'D' => 'Both statistical rarity and dysfunction may be used in classification.',
                ],
                'correct_option' => 'C',
                'points' => 1,
                'difficulty' => 'Normal',
                'explanation' => 'The statistical criterion defines abnormality as behaviors that are statistically infrequent or rare, not frequent.',
            ],
            [
                'order' => 3,
                'question_text' => 'Which of the following is true about the multicultural perspectives of abnormality?',
                'options' => [
                    'A' => 'All normal behaviors but not abnormal behaviors originate from a cultural context.',
                    'B' => 'It is reflected in the two cultural perspectives for explaining abnormality: cultural universality, and cultural relativism.',
                    'C' => 'All behaviors, both normal and abnormal, originate from cultural context.',
                    'D' => 'Biological origins of behavior are given more importance than interpersonal factors.',
                ],
                'correct_option' => 'B',
                'points' => 1,
                'difficulty' => 'Normal',
                'explanation' => 'Multicultural perspectives emphasize cultural universality (fixed cross-cultural patterns) and cultural relativism (culture-bound expressions of distress).',
            ],
            [
                'order' => 4,
                'question_text' => 'Edward, a 23-year-old law student, avoids recitations and social events because he fears being criticized or humiliated. He recognizes the fear as excessive but feels unable to control it. Symptoms have persisted for over 6 months. Most likely diagnosis?',
                'options' => [
                    'A' => 'Specific Phobia',
                    'B' => 'Social Anxiety Disorder',
                    'C' => 'Generalized Anxiety Disorder',
                    'D' => 'Avoidant Personality Disorder',
                ],
                'correct_option' => 'D',
                'points' => 1,
                'difficulty' => 'Normal',
                'explanation' => 'Pervasive avoidance of social activities due to feelings of inadequacy and hypersensitivity to negative evaluation persisting long-term.',
            ],
            [
                'order' => 5,
                'question_text' => 'Emily reports 8 months of excessive, uncontrollable worry about finances, school, health, and minor daily matters. She feels tense most days and has difficulty sleeping. Most likely diagnosis?',
                'options' => [
                    'A' => 'Social Anxiety Disorder',
                    'B' => 'Specific Phobia',
                    'C' => 'Posttraumatic Stress Disorder',
                    'D' => 'Generalized Anxiety Disorder',
                ],
                'correct_option' => 'D',
                'points' => 1,
                'difficulty' => 'Normal',
                'explanation' => 'Generalized Anxiety Disorder (GAD) is characterized by persistent, excessive, and uncontrollable worry across multiple life domains lasting at least 6 months.',
            ],
            [
                'order' => 6,
                'question_text' => 'A patient claims that people with mental disorders simply lack willpower and cannot recover. Which statement is accurate based on current evidence?',
                'options' => [
                    'A' => 'Mental disorders cannot be treated effectively.',
                    'B' => 'Mental disorders stem primarily from weak character.',
                    'C' => 'Mental disorders are not always inherited.',
                    'D' => 'Individuals with mental illness are easily recognizable.',
                ],
                'correct_option' => 'C',
                'points' => 1,
                'difficulty' => 'Normal',
                'explanation' => 'Mental disorders involve a complex interaction of biopsychosocial factors and are not purely hereditary.',
            ],
            [
                'order' => 7,
                'question_text' => 'During an interview, a patient laughs while describing her father’s funeral and begins crying while discussing a humorous childhood story. Which symptom is demonstrated?',
                'options' => [
                    'A' => 'Flattened affect',
                    'B' => 'Blunted affect',
                    'C' => 'Inappropriate affect',
                    'D' => 'Catatonia',
                ],
                'correct_option' => 'C',
                'points' => 1,
                'difficulty' => 'Normal',
                'explanation' => 'Inappropriate affect occurs when emotional expressions are incongruent or contradictory to the situational context or thought content.',
            ],
            [
                'order' => 8,
                'question_text' => 'Regarding schizophrenia, which statement about sex differences in age of onset is correct?',
                'options' => [
                    'A' => 'Males typically have later onset than females.',
                    'B' => 'Females typically develop schizophrenia earlier than males.',
                    'C' => 'Females tend to have later onset compared with males.',
                    'D' => 'Onset after age 65 is typical.',
                ],
                'correct_option' => 'C',
                'points' => 1,
                'difficulty' => 'Normal',
                'explanation' => 'Males typically experience onset in their late teens to early 20s, whereas females typically experience onset in their mid-to-late 20s or early 30s.',
            ],
            [
                'order' => 9,
                'question_text' => 'Which of the following is NOT a recognized gestational biological risk factor for schizophrenia?',
                'options' => [
                    'A' => 'Rhesus incompatibility',
                    'B' => 'Maternal malnutrition',
                    'C' => 'Winter birth effect',
                    'D' => 'Developmental fixation of the ego',
                ],
                'correct_option' => 'D',
                'points' => 1,
                'difficulty' => 'Normal',
                'explanation' => 'Developmental fixation of the ego is a psychodynamic concept, not an established biological gestational risk factor.',
            ],
            [
                'order' => 10,
                'question_text' => 'A patient is unable to maintain employment due to persistent hallucinations and disorganized behavior. This reflects which criterion of abnormality?',
                'options' => [
                    'A' => 'Deviance',
                    'B' => 'Dysfunction',
                    'C' => 'Distress',
                    'D' => 'Danger',
                ],
                'correct_option' => 'B',
                'points' => 1,
                'difficulty' => 'Normal',
                'explanation' => 'Dysfunction (maladaptiveness) refers to significant impairment in daily adaptive functioning, work, school, or interpersonal relationships.',
            ],
        ];
    }
}
