<?php

namespace Database\Seeders;

use App\Models\ClassModel;
use App\Models\MockBoard;
use App\Models\MockBoardAttempt;
use App\Models\MockBoardPhase;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\MockBoardStatisticsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountancyPresentationSeeder extends Seeder
{
    /**
     * Run the Accountancy presentation demo seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedAccountancyData();
        });
    }

    private function seedAccountancyData(): void
    {
        $teacherPassword = Hash::make('teacher123');
        $studentPassword = Hash::make('student123');

        // ─────────────────────────────────────────────────────────────
        // 1. Create Teacher Account (23-9999)
        // ─────────────────────────────────────────────────────────────
        $teacher = User::updateOrCreate(
            ['idnumber' => '23-9999'],
            [
                'name' => 'Prof. Maria Teresa Diaz, CPA',
                'email' => 'teacher.accountancy@reviso.com',
                'password' => $teacherPassword,
                'role' => 'teacher',
                'program' => 'accountancy',
                'status' => 'approved',
                'email_verified_at' => now(),
            ]
        );

        // ─────────────────────────────────────────────────────────────
        // 2. Create 10 Student Accounts (23-9991 to 23-9998, 23-10000, 23-10001)
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
                    'program' => 'accountancy',
                    'status' => 'approved',
                    'email_verified_at' => now(),
                ]
            );
            $students[] = $student;
        }

        // ─────────────────────────────────────────────────────────────
        // 3. Create Class & Enroll All 10 Students
        // ─────────────────────────────────────────────────────────────
        $class = ClassModel::updateOrCreate(
            ['code' => 'ACC401-2026'],
            [
                'name' => 'BSA 4-1: Financial Accounting & Reporting Review',
                'program' => 'accountancy',
                'school_year' => 2026,
                'year_level' => 4,
                'description' => 'Official CPALE Board Exam Review Class covering Financial Accounting and Reporting (FAR), Advanced Accounting, and Auditing Practice.',
                'created_by' => $teacher->id,
                'ai_summary' => 'The class demonstrates solid foundation in asset valuation and revenue recognition. Recommended focus areas include consolidation procedures under PFRS 10 and deferred taxes under PAS 12.',
                'assessment_ai_summary' => 'Class average stands at 78%. High proficiency observed in Auditing Principles and Ethics; continued drill recommended for analytical review and audit risk assessment.',
            ]
        );

        $studentIds = collect($students)->pluck('id')->toArray();
        $class->users()->syncWithoutDetaching($studentIds);

        // ─────────────────────────────────────────────────────────────
        // 4. Create 3 Class Modules (Pre-Test, Post-Test, Formal Assessment)
        // ─────────────────────────────────────────────────────────────
        // Module 1: Pre-Test
        $preTestModule = Module::updateOrCreate(
            ['class_id' => $class->id, 'title' => 'FAR Pre-Assessment: Conceptual Framework & Assets'],
            [
                'order' => 1,
                'is_quiz' => true,
                'is_formal_assessment' => false,
                'quiz_stage' => 'pre_test',
                'passing_grade' => 75,
                'time_limit' => 30,
                'is_active' => true,
                'created_by' => $teacher->id,
                'description' => 'Diagnostic pre-test evaluating baseline knowledge in Conceptual Framework and Asset Recognition.',
            ]
        );
        $this->seedQuestions($preTestModule, $this->getPreTestQuestions(), 'pre_test');

        // Module 2: Post-Test
        $postTestModule = Module::updateOrCreate(
            ['class_id' => $class->id, 'title' => 'FAR Post-Assessment: Liabilities & Advanced Topics'],
            [
                'order' => 2,
                'is_quiz' => true,
                'is_formal_assessment' => true,
                'quiz_stage' => 'post_test',
                'passing_grade' => 75,
                'time_limit' => 30,
                'is_active' => true,
                'created_by' => $teacher->id,
                'description' => 'Comprehensive post-test measuring mastery across financial liabilities and advanced accounting topics.',
            ]
        );
        $this->seedQuestions($postTestModule, $this->getPostTestQuestions(), 'post_test');

        // Module 3: Formal Assessment
        $formalAssessmentModule = Module::updateOrCreate(
            ['class_id' => $class->id, 'title' => 'Auditing Practice & Assurance - Midterm Assessment'],
            [
                'order' => 3,
                'is_quiz' => true,
                'is_formal_assessment' => true,
                'quiz_stage' => null,
                'passing_grade' => 75,
                'time_limit' => 45,
                'is_active' => true,
                'created_by' => $teacher->id,
                'description' => 'Formal examination on Auditing and Assurance Standards, Internal Control, and Audit Risk Model.',
            ]
        );
        $this->seedQuestions($formalAssessmentModule, $this->getFormalAssessmentQuestions(), null);

        // ─────────────────────────────────────────────────────────────
        // 5. Create Mock Board Exam with 2 Phases (Pre-Test & Pre-Boards)
        // ─────────────────────────────────────────────────────────────
        $adminUser = User::whereIn('role', ['admin', 'superadmin'])->first();

        $mockBoard = MockBoard::updateOrCreate(
            [
                'class_id' => $class->id,
                'title' => '2026 CPALE Comprehensive Mock Board Exam',
            ],
            [
                'teacher_id' => $teacher->id,
                'description' => 'Institutional Accountancy Licensure Simulation Exam with Pre-Test diagnostic and Pre-Boards final assessment phases.',
                'program' => 'accountancy',
                'review_period_start' => '2026-01-01',
                'review_period_end' => '2026-12-31',
                'passing_percentage' => 75,
                'visibility' => 'all',
                'status' => 'approved',
                'approved_by' => $adminUser ? $adminUser->id : $teacher->id,
                'approved_at' => now(),
            ]
        );

        // Mock Board Phase 1: Pre-Test
        $mbPreTestModule = Module::updateOrCreate(
            ['class_id' => $class->id, 'title' => '2026 CPALE Mock Board - Pre-Test Diagnostic'],
            [
                'is_quiz' => true,
                'is_formal_assessment' => true,
                'is_mock_board' => true,
                'passing_grade' => 75,
                'time_limit' => 60,
                'is_active' => true,
                'created_by' => $teacher->id,
                'description' => 'Diagnostic Mock Board phase evaluating readiness for CPA licensure topics.',
            ]
        );
        $this->seedQuestions($mbPreTestModule, $this->getMockBoardPreTestQuestions(), null);

        $mbPhasePreTest = MockBoardPhase::updateOrCreate(
            ['mock_board_id' => $mockBoard->id, 'phase_type' => 'pre_test'],
            [
                'sequence_number' => 1,
                'label' => 'Pre-Test',
                'title' => '2026 CPALE Mock Board - Pre-Test Diagnostic',
                'module_id' => $mbPreTestModule->id,
            ]
        );

        // Mock Board Phase 2: Pre-Boards (Post-Test)
        $mbPreBoardsModule = Module::updateOrCreate(
            ['class_id' => $class->id, 'title' => '2026 CPALE Mock Board - Pre-Boards Final Simulation'],
            [
                'is_quiz' => true,
                'is_formal_assessment' => true,
                'is_mock_board' => true,
                'passing_grade' => 75,
                'time_limit' => 60,
                'is_active' => true,
                'created_by' => $teacher->id,
                'description' => 'Final Board Simulation phase evaluating final readiness and score progression.',
            ]
        );
        $this->seedQuestions($mbPreBoardsModule, $this->getMockBoardPreBoardsQuestions(), null);

        $mbPhasePreBoards = MockBoardPhase::updateOrCreate(
            ['mock_board_id' => $mockBoard->id, 'phase_type' => 'pre_boards'],
            [
                'sequence_number' => 1,
                'label' => 'Pre-Boards',
                'title' => '2026 CPALE Mock Board - Pre-Boards Final Simulation',
                'module_id' => $mbPreBoardsModule->id,
            ]
        );

        // ─────────────────────────────────────────────────────────────
        // 6. Seed Student Quiz Attempts, Answers & Mock Board Attempts
        // ─────────────────────────────────────────────────────────────
        // Score matrices (out of 10) designed for statistically valid distributions:
        // Diagnostic Pre-Tests show modest scores; Post-Tests & Pre-Boards show strong mastery gains.
        $scoreMatrix = [
            // Student 0: 23-9991 Juan Dela Cruz (Top)
            0 => ['class_pre' => 8, 'class_post' => 10, 'class_formal' => 9, 'mb_pre' => 8, 'mb_post' => 10],
            // Student 1: 23-9992 Bea Alonzo (High)
            1 => ['class_pre' => 7, 'class_post' => 9, 'class_formal' => 9, 'mb_pre' => 7, 'mb_post' => 9],
            // Student 2: 23-9993 Joshua Santos (Above Average)
            2 => ['class_pre' => 7, 'class_post' => 8, 'class_formal' => 8, 'mb_pre' => 6, 'mb_post' => 9],
            // Student 3: 23-9994 Katherine Bernardo (Good)
            3 => ['class_pre' => 6, 'class_post' => 8, 'class_formal' => 8, 'mb_pre' => 6, 'mb_post' => 8],
            // Student 4: 23-9995 Daniel Padilla (Average)
            4 => ['class_pre' => 6, 'class_post' => 7, 'class_formal' => 7, 'mb_pre' => 5, 'mb_post' => 8],
            // Student 5: 23-9996 Liza Soberano (Average)
            5 => ['class_pre' => 5, 'class_post' => 8, 'class_formal' => 7, 'mb_pre' => 5, 'mb_post' => 7],
            // Student 6: 23-9997 Enrique Gil (Moderate)
            6 => ['class_pre' => 5, 'class_post' => 7, 'class_formal' => 6, 'mb_pre' => 4, 'mb_post' => 7],
            // Student 7: 23-9998 Nadine Lustre (Moderate)
            7 => ['class_pre' => 4, 'class_post' => 6, 'class_formal' => 6, 'mb_pre' => 4, 'mb_post' => 6],
            // Student 8: 23-10000 James Reid (Developing)
            8 => ['class_pre' => 4, 'class_post' => 6, 'class_formal' => 5, 'mb_pre' => 3, 'mb_post' => 6],
            // Student 9: 23-10001 Kathryn Manuel (Developing)
            9 => ['class_pre' => 3, 'class_post' => 5, 'class_formal' => 5, 'mb_pre' => 4, 'mb_post' => 6],
        ];

        foreach ($students as $index => $student) {
            $scores = $scoreMatrix[$index];

            // 1. Class Pre-Test Attempt
            $this->createAttemptAndAnswers(
                student: $student,
                module: $preTestModule,
                score: $scores['class_pre'],
                quizStage: 'pre_test',
                mockBoard: null,
                phase: null
            );

            // 2. Class Post-Test Attempt
            $this->createAttemptAndAnswers(
                student: $student,
                module: $postTestModule,
                score: $scores['class_post'],
                quizStage: 'post_test',
                mockBoard: null,
                phase: null
            );

            // 3. Class Formal Assessment Attempt
            $this->createAttemptAndAnswers(
                student: $student,
                module: $formalAssessmentModule,
                score: $scores['class_formal'],
                quizStage: null,
                mockBoard: null,
                phase: null
            );

            // 4. Mock Board Pre-Test Attempt
            $this->createAttemptAndAnswers(
                student: $student,
                module: $mbPreTestModule,
                score: $scores['mb_pre'],
                quizStage: 'pre_test',
                mockBoard: $mockBoard,
                phase: $mbPhasePreTest
            );

            // 5. Mock Board Pre-Boards Attempt
            $this->createAttemptAndAnswers(
                student: $student,
                module: $mbPreBoardsModule,
                score: $scores['mb_post'],
                quizStage: 'post_test',
                mockBoard: $mockBoard,
                phase: $mbPhasePreBoards
            );
        }

        // ─────────────────────────────────────────────────────────────
        // 7. Compute & Save ANOVA Statistics for Mock Board
        // ─────────────────────────────────────────────────────────────
        try {
            app(MockBoardStatisticsService::class)->computeClassStatistics($mockBoard);
        } catch (\Throwable $e) {
            // Ignore if service calculation succeeds later during UI load
        }

        // ─────────────────────────────────────────────────────────────
        // 8. Seed Second Mock Board Exam (with At-Risk Student Attempt)
        // ─────────────────────────────────────────────────────────────
        $this->seedSecondMockBoard($teacher, $class, $students, $adminUser);
    }

    /**
     * Seed 10 questions into a module.
     */
    private function seedQuestions(Module $module, array $questions, ?string $quizStage): void
    {
        foreach ($questions as $index => $q) {
            QuizQuestion::updateOrCreate(
                [
                    'module_id' => $module->id,
                    'order' => $index + 1,
                ],
                [
                    'quiz_stage' => $quizStage,
                    'question_text' => $q['text'],
                    'options' => $q['options'],
                    'correct_option' => $q['correct'],
                    'points' => 1,
                    'difficulty' => $q['difficulty'] ?? 'Normal',
                    'domain' => $q['domain'] ?? 'Accountancy',
                    'explanation' => $q['explanation'],
                ]
            );
        }
    }

    /**
     * Create completed QuizAttempt, QuizAnswers, and MockBoardAttempt (if applicable).
     */
    private function createAttemptAndAnswers(
        User $student,
        Module $module,
        int $score,
        ?string $quizStage,
        ?MockBoard $mockBoard,
        ?MockBoardPhase $phase
    ): void {
        $total = 10;
        $percentage = (int) round(($score / $total) * 100);
        $passed = $percentage >= ($module->passing_grade ?? 75);

        // Delete any existing attempts for idempotency
        $existingAttempt = QuizAttempt::where('user_id', $student->id)
            ->where('module_id', $module->id)
            ->first();

        if ($existingAttempt) {
            QuizAnswer::where('attempt_id', $existingAttempt->id)->delete();
            if ($mockBoard) {
                MockBoardAttempt::where('quiz_attempt_id', $existingAttempt->id)->delete();
            }
            $existingAttempt->delete();
        }

        $attempt = QuizAttempt::create([
            'user_id' => $student->id,
            'module_id' => $module->id,
            'quiz_stage' => $quizStage,
            'mock_board_id' => $mockBoard ? $mockBoard->id : null,
            'mock_board_phase_type' => $phase ? $phase->phase_type : null,
            'score' => $score,
            'total' => $total,
            'percentage' => $percentage,
            'passed' => $passed,
            'status' => 'completed',
            'started_at' => now()->subHours(3),
            'completed_at' => now()->subHours(2),
            'attempted_at' => now()->subHours(2),
            'attempt_count' => 1,
            'ai_strong' => 'Demonstrates proficiency in conceptual framework fundamentals and standard asset recognition criteria.',
            'ai_weak' => 'Requires reinforcement in multi-step problem solving and complex valuation adjustments.',
            'ai_recommendation' => 'Review comprehensive practice sets and test bank questions for the lower-scoring topics.',
        ]);

        // Load questions for this module
        $questions = $module->quizQuestions()->orderBy('order')->get();

        // Determine which questions the student gets right vs wrong
        // Distribute correct answers across indices based on $score
        $questionIndices = range(0, count($questions) - 1);
        // Deterministic variation per student ID
        $seed = ($student->id * 17) % count($questionIndices);
        $correctIndices = [];
        for ($i = 0; $i < $score; $i++) {
            $correctIndices[] = ($seed + $i) % count($questions);
        }

        $allOptions = ['A', 'B', 'C', 'D'];

        foreach ($questions as $qIndex => $question) {
            $isCorrect = in_array($qIndex, $correctIndices, true);

            if ($isCorrect) {
                $selected = $question->correct_option;
            } else {
                // Select a plausible distractor (different from correct)
                $distractors = array_values(array_diff($allOptions, [$question->correct_option]));
                $distractorIndex = ($student->id + $qIndex) % count($distractors);
                $selected = $distractors[$distractorIndex];
            }

            QuizAnswer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'selected_option' => $selected,
                'is_correct' => $isCorrect,
            ]);
        }

        // Mock board attempt link
        if ($mockBoard && $phase) {
            MockBoardAttempt::create([
                'user_id' => $student->id,
                'mock_board_id' => $mockBoard->id,
                'mock_board_phase_id' => $phase->id,
                'phase_type' => $phase->phase_type,
                'quiz_attempt_id' => $attempt->id,
                'score' => $score,
                'total' => $total,
                'percentage' => $percentage,
                'passed' => $passed,
                'attempt_count' => 1,
                'ai_strong' => 'Strong performance on foundational accounting standards and audit governance.',
                'ai_weak' => 'Review needed for nuanced disclosures and subsequent event assessments.',
                'ai_recommendation' => 'Continue timed drills to enhance speed and precision during board examinations.',
            ]);
        }

        // Record module progress
        ModuleProgress::updateOrCreate(
            ['module_id' => $module->id, 'user_id' => $student->id],
            [
                'progress' => 100.00,
                'completed' => true,
                'completed_at' => now()->subHours(2),
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // QUESTION SETS (Actual Philippine CPA Board Exam Curriculum)
    // ─────────────────────────────────────────────────────────────

    private function getPreTestQuestions(): array
    {
        return [
            [
                'text' => 'According to the Revised Conceptual Framework for Financial Reporting, what are the two fundamental qualitative characteristics of useful financial information?',
                'options' => [
                    'A' => 'Relevance and Faithful Representation',
                    'B' => 'Comparability and Verifiability',
                    'C' => 'Timeliness and Understandability',
                    'D' => 'Materiality and Prudence',
                ],
                'correct' => 'A',
                'difficulty' => 'Easy',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under Chapter 2 of the Conceptual Framework, the fundamental qualitative characteristics are relevance and faithful representation. Comparability, verifiability, timeliness, and understandability are enhancing characteristics.',
            ],
            [
                'text' => 'Which of the following items should be classified as Cash and Cash Equivalents in the Statement of Financial Position?',
                'options' => [
                    'A' => 'A 6-month time deposit with a commercial bank',
                    'B' => 'Post-dated customer checks awaiting clearing',
                    'C' => 'Treasury bills acquired 60 days before maturity date',
                    'D' => 'Bond sinking fund cash restricted for settlement in 3 years',
                ],
                'correct' => 'C',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Cash equivalents are short-term, highly liquid investments that are readily convertible to known amounts of cash and subject to an insignificant risk of changes in value, typically with an original maturity of three months (90 days) or less from acquisition date.',
            ],
            [
                'text' => 'Under PAS 2 (Inventories), inventories must be measured at:',
                'options' => [
                    'A' => 'Lower of cost and net realizable value (LCNRV)',
                    'B' => 'Fair value less costs of disposal',
                    'C' => 'Historical cost without allowance for decline in value',
                    'D' => 'Replacement cost or net selling price, whichever is higher',
                ],
                'correct' => 'A',
                'difficulty' => 'Easy',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PAS 2 paragraph 9 clearly mandates that inventories shall be measured at the lower of cost and net realizable value (LCNRV).',
            ],
            [
                'text' => 'When using the aging method to estimate credit losses on trade accounts receivable, the calculated allowance represents the:',
                'options' => [
                    'A' => 'Impairment loss expense to be added directly to the beginning allowance',
                    'B' => 'Required ending balance of the allowance for expected credit losses',
                    'C' => 'Total write-offs incurred during the accounting period',
                    'D' => 'Gross amount of accounts receivable collectible within one year',
                ],
                'correct' => 'B',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'The aging of accounts receivable is a Statement of Financial Position approach. The computed amount yields the required ending balance of the allowance account. Bad debt expense is the plug figure.',
            ],
            [
                'text' => 'Under PAS 16, which of the following costs incurred during construction of an item of Property, Plant, and Equipment should be capitalized?',
                'options' => [
                    'A' => 'Costs of opening a new facility or inaugurating the site',
                    'B' => 'Costs of employee benefits arising directly from the construction',
                    'C' => 'Initial operating losses incurred before reaching planned performance',
                    'D' => 'General administrative and corporate overhead costs',
                ],
                'correct' => 'B',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PAS 16 explicitly includes directly attributable costs such as employee benefits arising directly from the construction or acquisition of the item. Opening costs, operating losses, and general administrative overhead are expensed as incurred.',
            ],
            [
                'text' => 'Under PAS 38, how should research costs and development costs be treated?',
                'options' => [
                    'A' => 'Both research and development costs must always be capitalized',
                    'B' => 'Both research and development costs must always be expensed',
                    'C' => 'Research costs are expensed as incurred; development costs meeting criteria are capitalized',
                    'D' => 'Research costs are capitalized; development costs are expensed',
                ],
                'correct' => 'C',
                'difficulty' => 'Easy',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PAS 38 prohibits capitalization of research phase costs because an entity cannot demonstrate that an intangible asset exists that will generate future economic benefits. Development costs can be capitalized only when all 6 strict criteria (technical feasibility, intent, ability, etc.) are met.',
            ],
            [
                'text' => 'Under PAS 40, an investment property measured under the Fair Value Model has fair value changes recognized in:',
                'options' => [
                    'A' => 'Other Comprehensive Income (OCI) and accumulated in revaluation surplus',
                    'B' => 'Profit or Loss for the period in which the change arises',
                    'C' => 'Retained Earnings directly as a capital reserve adjustment',
                    'D' => 'Deferred income liability until the property is disposed',
                ],
                'correct' => 'B',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under PAS 40 Fair Value Model, any gain or loss arising from a change in the fair value of investment property is recognized in profit or loss in the period in which it arises, distinct from the revaluation model of PAS 16.',
            ],
            [
                'text' => 'Under PFRS 5, a non-current asset classified as held for sale must be measured at:',
                'options' => [
                    'A' => 'Lower of carrying amount and fair value less costs to sell',
                    'B' => 'Fair value less costs to sell without reference to carrying amount',
                    'C' => 'Carrying amount and depreciated over its remaining physical life',
                    'D' => 'Net realizable value with continued depreciation',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PFRS 5 specifies that non-current assets held for sale are measured at the lower of carrying amount and fair value less costs to sell, and depreciation ceases immediately upon classification.',
            ],
            [
                'text' => 'Under PAS 37, a provision must be recognized in the financial statements when:',
                'options' => [
                    'A' => 'An entity has a present obligation, an outflow of resources is probable, and a reliable estimate can be made',
                    'B' => 'An entity has a possible obligation regardless of outflow probability',
                    'C' => 'An outflow of resources is remotely possible and estimated by management',
                    'D' => 'A future planned restructuring has been discussed by executive management without announcement',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PAS 37 paragraph 14 requires three criteria for provision recognition: (1) present obligation as a result of a past event, (2) probable outflow of resources embodying economic benefits, and (3) a reliable estimate can be made.',
            ],
            [
                'text' => 'Under PAS 36, an asset is impaired when its carrying amount exceeds its recoverable amount. The recoverable amount is defined as the:',
                'options' => [
                    'A' => 'Higher of fair value less costs of disposal and value in use',
                    'B' => 'Lower of fair value less costs of disposal and value in use',
                    'C' => 'Net realizable value less future disposal expenditures',
                    'D' => 'Undiscounted expected future cash flows from the asset',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under PAS 36 paragraph 18, recoverable amount is the higher of an asset\'s fair value less costs of disposal and its value in use (discounted future cash flows).',
            ],
        ];
    }

    private function getPostTestQuestions(): array
    {
        return [
            [
                'text' => 'When bonds are issued at a discount, the carrying amount of the bond liability over its term:',
                'options' => [
                    'A' => 'Increases towards face value due to amortization of the discount',
                    'B' => 'Decreases towards zero as interest payments are made',
                    'C' => 'Remains constant until redemption date',
                    'D' => 'Fluctuates inversely with market rates of interest',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under the effective interest method, discount amortization increases the carrying amount of bonds payable each period until it equals the face value at maturity.',
            ],
            [
                'text' => 'Under PFRS 16 (Leases), how does a lessee initially measure a lease liability?',
                'options' => [
                    'A' => 'At the undiscounted total of future lease payments',
                    'B' => 'At the present value of lease payments not paid at commencement date, discounted using the interest rate implicit in the lease',
                    'C' => 'At the fair value of the underlying asset being leased',
                    'D' => 'At the nominal monthly rental multiplied by twelve months',
                ],
                'correct' => 'B',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PFRS 16 paragraph 26 states that at commencement date, the lease liability is measured at the present value of lease payments not paid at that date, discounted using the interest rate implicit in the lease or incremental borrowing rate.',
            ],
            [
                'text' => 'Under PAS 12 (Income Taxes), a deductible temporary difference gives rise to a:',
                'options' => [
                    'A' => 'Deferred Tax Liability',
                    'B' => 'Deferred Tax Asset, subject to the probability of taxable profit availability',
                    'C' => 'Permanent tax difference recognized directly in equity',
                    'D' => 'Prior period tax adjustment in retained earnings',
                ],
                'correct' => 'B',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Deductible temporary differences result in amounts that are deductible in determining future taxable profit. They give rise to a Deferred Tax Asset to the extent that it is probable taxable profit will be available.',
            ],
            [
                'text' => 'Under PAS 19 (Employee Benefits), remeasurements of the net defined benefit liability (asset) are recognized in:',
                'options' => [
                    'A' => 'Profit or loss as part of operating expenses',
                    'B' => 'Other Comprehensive Income (OCI) and never reclassified to profit or loss',
                    'C' => 'Other Comprehensive Income (OCI) and subsequently reclassified to P&L',
                    'D' => 'Direct reduction of share premium in equity',
                ],
                'correct' => 'B',
                'difficulty' => 'Hard',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PAS 19 states that remeasurements (actuarial gains/losses, return on plan assets excluding net interest) shall be recognized in OCI and shall not be reclassified to profit or loss in a subsequent period.',
            ],
            [
                'text' => 'When treasury shares are reissued at a price higher than their acquisition cost, the excess is credited to:',
                'options' => [
                    'A' => 'Gain on Sale of Treasury Shares in Profit or Loss',
                    'B' => 'Share Premium - Treasury Shares',
                    'C' => 'Retained Earnings as unappropriated surplus',
                    'D' => 'Other Comprehensive Income',
                ],
                'correct' => 'B',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under PAS 32, no gain or loss is recognized in profit or loss on the purchase, sale, issue, or cancellation of an entity\'s own equity instruments. Any excess received over cost is credited to Share Premium - Treasury Shares.',
            ],
            [
                'text' => 'In computing diluted earnings per share (EPS) under PAS 33, convertible bonds are assumed to have been converted at the beginning of the period. This adjustment requires:',
                'options' => [
                    'A' => 'Adding back after-tax bond interest expense to net income and adding converted shares to weighted average shares',
                    'B' => 'Deducting bond interest expense from net income and deducting shares from weighted average shares',
                    'C' => 'Adjusting only the denominator without any change to the numerator',
                    'D' => 'Ignoring convertible bonds if the conversion price exceeds market value',
                ],
                'correct' => 'A',
                'difficulty' => 'Hard',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under the "if-converted" method for convertible bonds, net income is increased by the bond interest expense saved net of tax (numerator), and weighted average shares are increased by the number of shares issuable upon conversion (denominator).',
            ],
            [
                'text' => 'Under PFRS 15, what is the third step in the 5-step model for revenue recognition?',
                'options' => [
                    'A' => 'Identify the contract with a customer',
                    'B' => 'Identify the performance obligations in the contract',
                    'C' => 'Determine the transaction price',
                    'D' => 'Allocate the transaction price to performance obligations',
                ],
                'correct' => 'C',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'The 5 steps are: (1) Identify contract, (2) Identify performance obligations, (3) Determine transaction price, (4) Allocate transaction price, (5) Recognize revenue when/as performance obligations are satisfied.',
            ],
            [
                'text' => 'A change in depreciation method from straight-line to double declining balance is accounted for under PAS 8 as a:',
                'options' => [
                    'A' => 'Change in accounting policy requiring retrospective restatement',
                    'B' => 'Correction of a prior period error with opening retained earnings adjustment',
                    'C' => 'Change in accounting estimate applied prospectively',
                    'D' => 'Fundamental error requiring note disclosure only',
                ],
                'correct' => 'C',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under PAS 16 and PAS 8, a change in depreciation method, residual value, or useful life reflects a change in the expected pattern of consumption of future economic benefits and is accounted for as a change in accounting estimate (prospective).',
            ],
            [
                'text' => 'In a statement of cash flows prepared under PAS 7, cash payments to acquire property, plant, and equipment are classified under:',
                'options' => [
                    'A' => 'Operating activities',
                    'B' => 'Investing activities',
                    'C' => 'Financing activities',
                    'D' => 'Non-cash supplemental disclosures',
                ],
                'correct' => 'A',
                'difficulty' => 'Easy',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PAS 7 defines investing activities as the acquisition and disposal of long-term assets and other investments not included in cash equivalents. Cash payments to acquire PPE are investing cash outflows.',
            ],
            [
                'text' => 'Under PFRS 10, when a parent acquires control of an 80%-owned subsidiary, goodwill is recognized. How is Non-Controlling Interest (NCI) measured on acquisition date under PFRS 3?',
                'options' => [
                    'A' => 'At fair value or at the NCI proportionate share of the acquiree identifiable net assets',
                    'B' => 'Strictly at historical book value of subsidiary net assets',
                    'C' => 'At the parent purchase price prorated to the 20% interest',
                    'D' => 'Always at zero until dividends are formally declared',
                ],
                'correct' => 'A',
                'difficulty' => 'Hard',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PFRS 3 paragraph 19 allows the acquirer to measure non-controlling interest on a transaction-by-transaction basis either at fair value (full goodwill method) or at the NCI proportionate share of the acquiree\'s identifiable net assets (proportionate goodwill method).',
            ],
        ];
    }

    private function getFormalAssessmentQuestions(): array
    {
        return [
            [
                'text' => 'According to PSA 200, what is the overall objective of the independent auditor in conducting an audit of financial statements?',
                'options' => [
                    'A' => 'To obtain reasonable assurance about whether the financial statements as a whole are free from material misstatement, whether due to fraud or error',
                    'B' => 'To guarantee that no undetected errors or fraudulent activities exist within the entity',
                    'C' => 'To evaluate management operating efficiency and business strategy viability',
                    'D' => 'To prepare and certify the accuracy of internal trial balances and ledgers',
                ],
                'correct' => 'A',
                'difficulty' => 'Easy',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 200 paragraph 11 states that the auditor\'s objective is to obtain reasonable assurance that the financial statements as a whole are free from material misstatement, enabling an opinion on whether they are prepared in accordance with the applicable financial reporting framework.',
            ],
            [
                'text' => 'In the Audit Risk Model, which component of audit risk is under the direct control of the independent auditor?',
                'options' => [
                    'A' => 'Inherent Risk',
                    'B' => 'Control Risk',
                    'C' => 'Detection Risk',
                    'D' => 'Business Risk',
                ],
                'correct' => 'C',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'Inherent risk and control risk are the entity\'s risks of material misstatement (RMM) and exist independently of the audit. Detection risk is controlled by the auditor through the nature, timing, and extent of substantive audit procedures.',
            ],
            [
                'text' => 'Under PSA 320, "Performance Materiality" is set by the auditor to:',
                'options' => [
                    'A' => 'Establish the highest threshold below which misstatements are completely ignored',
                    'B' => 'Reduce to an appropriately low level the probability that the aggregate of uncorrected and undetected misstatements exceeds materiality for the financial statements as a whole',
                    'C' => 'Determine the total audit fee based on client gross assets',
                    'D' => 'Comply with tax authority reporting requirements',
                ],
                'correct' => 'B',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 320 paragraph 9 defines performance materiality as the amount set by the auditor at less than materiality for the financial statements as a whole to reduce to an appropriately low level the probability that the aggregate of uncorrected and undetected misstatements exceeds overall materiality.',
            ],
            [
                'text' => 'Which of the following is NOT one of the five interrelated components of Internal Control under the COSO Framework and PSA 315?',
                'options' => [
                    'A' => 'Control Environment',
                    'B' => 'Risk Assessment Process',
                    'C' => 'External Regulatory Enforcement',
                    'D' => 'Control Activities and Monitoring',
                ],
                'correct' => 'C',
                'difficulty' => 'Easy',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'The 5 COSO internal control components adopted in PSA 315 are: (1) Control Environment, (2) Entity\'s Risk Assessment Process, (3) Information System and Communication, (4) Control Activities, and (5) Monitoring of Controls. External regulatory enforcement is an external environmental factor.',
            ],
            [
                'text' => 'Under PSA 500 (Audit Evidence), audit evidence is considered more reliable when it is:',
                'options' => [
                    'A' => 'Obtained from knowledgeable independent sources outside the entity being audited',
                    'B' => 'Generated purely through oral inquiries of management without corroboration',
                    'C' => 'Photocopied or digitized without verifying the original underlying documents',
                    'D' => 'Prepared during the planning phase before internal control testing',
                ],
                'correct' => 'A',
                'difficulty' => 'Easy',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 500 states that audit evidence is more reliable when obtained from independent sources outside the entity, when existing controls are effective, when obtained directly by the auditor, and when in documentary form rather than oral representations.',
            ],
            [
                'text' => 'When sending positive accounts receivable confirmations under PSA 505 and a customer fails to respond after second requests, the auditor should:',
                'options' => [
                    'A' => 'Automatically write off the customer account as fraudulent',
                    'B' => 'Perform alternative audit procedures such as examining subsequent cash receipts and shipping documents',
                    'C' => 'Issue an immediate disclaimer of audit opinion',
                    'D' => 'Accept management verbal assurance that the balance is fully collectible',
                ],
                'correct' => 'B',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 505 paragraph 12 requires that in the case of non-response to positive confirmation requests, the auditor shall perform alternative audit procedures to obtain relevant and reliable audit evidence (such as subsequent collections, shipping notices, customer orders).',
            ],
            [
                'text' => 'Tests of controls are audit procedures designed to evaluate the:',
                'options' => [
                    'A' => 'Operating effectiveness of controls in preventing, or detecting and correcting, material misstatements at the assertion level',
                    'B' => 'Monetary accuracy of transactions recorded in the general ledger',
                    'C' => 'Fair value of financial instruments using valuation models',
                    'D' => 'Existence of unrecorded liabilities at year-end',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'Under PSA 330, tests of controls evaluate the operating effectiveness of controls in preventing, or detecting and correcting, material misstatements at the assertion level. Substantive tests evaluate monetary misstatements.',
            ],
            [
                'text' => 'Under PSA 560 (Subsequent Events), an event occurring after the reporting period that provides evidence of conditions that existed at the end of the reporting period is an:',
                'options' => [
                    'A' => 'Adjusting event requiring adjustment of the amounts recognized in the financial statements',
                    'B' => 'Non-adjusting event requiring disclosure only in the notes',
                    'C' => 'Emergency contingent liability that does not affect current statements',
                    'D' => 'Audit scope limitation requiring an adverse opinion',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 560 and PAS 10 define adjusting events as those providing evidence of conditions that existed at the end of the reporting period (e.g., settlement of court case confirming present obligation at year-end, bankruptcy of customer indicating impairment of year-end receivable).',
            ],
            [
                'text' => 'When the auditor concludes that the financial statements are materially misstated and the effect of the misstatement is BOTH material and pervasive, the auditor must express a(n):',
                'options' => [
                    'A' => 'Qualified Opinion with "except for" phrasing',
                    'B' => 'Adverse Opinion',
                    'C' => 'Disclaimer of Opinion',
                    'D' => 'Unmodified Opinion with an Emphasis of Matter paragraph',
                ],
                'correct' => 'B',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'Under PSA 705 paragraph 8, when the auditor obtains sufficient appropriate audit evidence and concludes that misstatements are individually or in the aggregate both material and pervasive to the financial statements, the auditor shall express an Adverse Opinion.',
            ],
            [
                'text' => 'Under PSA 701, "Key Audit Matters" (KAM) are defined as those matters that, in the auditor\'s professional judgment:',
                'options' => [
                    'A' => 'Were of most significance in the audit of the financial statements of the current period',
                    'B' => 'Resulted in a qualified or adverse modification of the auditor\'s opinion',
                    'C' => 'Involve violations of criminal laws by members of the board of directors',
                    'D' => 'Disclose confidential client pricing models to competitive peers',
                ],
                'correct' => 'A',
                'difficulty' => 'Hard',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 701 paragraph 8 defines Key Audit Matters as those matters that, in the auditor\'s professional judgment, were of most significance in the audit of the financial statements of the current period, selected from matters communicated with those charged with governance.',
            ],
        ];
    }

    private function getMockBoardPreTestQuestions(): array
    {
        return [
            [
                'text' => 'In cash flow reporting under PAS 7, how should interest paid and dividends received be classified by an entity other than a financial institution?',
                'options' => [
                    'A' => 'Interest paid may be operating or financing; dividends received may be operating or investing',
                    'B' => 'Interest paid must always be investing; dividends received must always be financing',
                    'C' => 'Both must always be classified strictly as financing cash flows',
                    'D' => 'Both must always be classified strictly as operating cash flows with no alternative',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PAS 7 paragraph 33 permits interest paid to be classified as operating cash flow (enters into P&L) or financing cash flow (cost of obtaining financial resources). Dividends received may be classified as operating cash flow or investing cash flow (return on investments).',
            ],
            [
                'text' => 'Under PFRS 9, when an entity irrevocably designates an investment in equity instruments at Fair Value through Other Comprehensive Income (FVOCI), cumulative gains or losses in OCI upon derecognition:',
                'options' => [
                    'A' => 'Are recycled to profit or loss as an impairment reversal',
                    'B' => 'Are transferred directly within equity to retained earnings without passing through profit or loss',
                    'C' => 'Must remain indefinitely in OCI and cannot be reclassified to retained earnings',
                    'D' => 'Are reclassified to share premium capital reserves',
                ],
                'correct' => 'B',
                'difficulty' => 'Hard',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under PFRS 9 paragraph B5.7.1, for equity instruments designated at FVOCI, gains and losses recognized in OCI are not subsequently transferred to profit or loss. However, the entity may transfer the cumulative gain or loss within equity (to retained earnings).',
            ],
            [
                'text' => 'Under PAS 41 (Agriculture), a biological asset shall be measured on initial recognition and at the end of each reporting period at:',
                'options' => [
                    'A' => 'Fair value less costs to sell',
                    'B' => 'Historical cost less accumulated depreciation',
                    'C' => 'Net realizable value based on historical crop yields',
                    'D' => 'Replacement cost or market value, whichever is lower',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PAS 41 paragraph 12 specifies that a biological asset shall be measured on initial recognition and at the end of each reporting period at its fair value less costs to sell, except where fair value cannot be measured reliably.',
            ],
            [
                'text' => 'Under PAS 20 (Government Grants), a government grant related to an asset should be presented in the Statement of Financial Position either as:',
                'options' => [
                    'A' => 'Deferred income or by deducting the grant in arriving at the carrying amount of the asset',
                    'B' => 'Immediate revenue in profit or loss upon receipt or revaluation surplus in equity',
                    'C' => 'A permanent component of share premium capital',
                    'D' => 'A contingent liability in the notes to financial statements',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PAS 20 paragraph 24 permits two acceptable methods of presentation for grants related to assets: (1) setting up the grant as deferred income, or (2) deducting the grant in arriving at the carrying amount of the asset.',
            ],
            [
                'text' => 'Under PSA 240, what are the two types of intentional misstatements that are relevant to the auditor\'s consideration of fraud?',
                'options' => [
                    'A' => 'Misstatements resulting from fraudulent financial reporting and misstatements resulting from misappropriation of assets',
                    'B' => 'Unintentional clerical mathematical errors and misapplication of accounting estimates',
                    'C' => 'Bribery of public officials and violation of occupational safety codes',
                    'D' => 'Overstatement of cash balances and improper inventory count tags',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 240 paragraph 3 distinguishes two types of intentional misstatements relevant to the auditor: misstatements resulting from fraudulent financial reporting (cooking the books) and misstatements resulting from misappropriation of assets (theft/stealing).',
            ],
            [
                'text' => 'Substantive analytical procedures are most appropriate and effective when applied to:',
                'options' => [
                    'A' => 'Large volumes of predictable transactions over time, such as payroll or interest income',
                    'B' => 'Unique, non-routine asset acquisitions and disposals occurring at year-end',
                    'C' => 'Highly confidential management bonuses negotiated verbally',
                    'D' => 'Complex business combinations with unlisted subsidiaries',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'Under PSA 520, substantive analytical procedures generally provide more persuasive evidence when applied to large volumes of predictable transactions (e.g. payroll expenses, rental revenue, interest calculations on debt).',
            ],
            [
                'text' => 'When an auditor assesses whether an entity has the ability to continue as a going concern under PSA 570, the assessment period covered by management must be at least:',
                'options' => [
                    'A' => '12 months from the end of the reporting period',
                    'B' => '6 months from the date of the auditor\'s report',
                    'C' => '3 years from the date of incorporation',
                    'D' => '1 fiscal quarter following board approval',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 570 and PAS 1 require management to make an assessment of the entity\'s ability to continue as a going concern covering at least twelve months from the end of the reporting period.',
            ],
            [
                'text' => 'Under PFRS 15, when a contract contains a significant financing component, the entity adjusts the promised amount of consideration to reflect the:',
                'options' => [
                    'A' => 'Cash selling price that the customer would have paid at the time of transfer of goods or services',
                    'B' => 'Future value of payments compounded at prime bank rates',
                    'C' => 'Replacement cost of goods plus standard manufacturer markup',
                    'D' => 'Nominal contract price without discounting',
                ],
                'correct' => 'A',
                'difficulty' => 'Hard',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under PFRS 15 paragraph 61, the objective when adjusting for a significant financing component is for an entity to recognize revenue at an amount that reflects the price that a customer would have paid for the promised goods or services if the customer had paid cash for those goods or services when (or as) they transfer to the customer (i.e. the cash selling price).',
            ],
            [
                'text' => 'Under PSA 300, which of the following activities is performed during the audit planning phase to establish the overall audit strategy?',
                'options' => [
                    'A' => 'Determining the scope of the engagement, reporting objectives, and key audit timing milestones',
                    'B' => 'Executing detailed confirmation tests of year-end trade payables',
                    'C' => 'Issuing the final formal auditor\'s report to shareholders',
                    'D' => 'Performing bank reconciliations for all closed operating accounts',
                ],
                'correct' => 'A',
                'difficulty' => 'Easy',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 300 paragraph 7 states that the auditor establishes the overall audit strategy by identifying the characteristics of the engagement that define its scope, ascertaining reporting objectives to plan timing, and considering factors significant in directing audit team efforts.',
            ],
            [
                'text' => 'Under PSA 706, an Emphasis of Matter paragraph in an independent auditor\'s report is used to:',
                'options' => [
                    'A' => 'Draw users\' attention to a matter appropriately presented or disclosed in the financial statements that is fundamental to users\' understanding',
                    'B' => 'Substitute for an adverse opinion when financial statements contain pervasive GAAP departures',
                    'C' => 'Disclose confidential client irregularities that management refused to publish in notes',
                    'D' => 'Provide a clean audit clearance without completing substantive testing',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 706 defines an Emphasis of Matter paragraph as a paragraph included in the auditor\'s report that refers to a matter appropriately presented or disclosed in the financial statements that, in the auditor\'s judgment, is of such importance that it is fundamental to users\' understanding of the financial statements.',
            ],
        ];
    }

    private function getMockBoardPreBoardsQuestions(): array
    {
        return [
            [
                'text' => 'When accounting for compound financial instruments (such as convertible bonds with detachable warrants) under PAS 32, how is the initial proceeds allocated?',
                'options' => [
                    'A' => 'First to the liability component at fair value of a similar liability without conversion feature, with the residual amount assigned to equity',
                    'B' => 'First to the equity component at fair value, with residual assigned to liability',
                    'C' => 'Equally (50-50) between liability and equity regardless of market yields',
                    'D' => 'Entirely to liability until warrants are formally exercised by holders',
                ],
                'correct' => 'A',
                'difficulty' => 'Hard',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under PAS 32 paragraph 31, compound financial instruments are split using the residual method: the liability component is measured first at the fair value of a similar liability that does not have an associated equity component, and the residual amount is assigned to the equity component.',
            ],
            [
                'text' => 'Under PFRS 2 (Share-based Payment), for equity-settled share-based payment transactions with employees, the goods or services received and the corresponding increase in equity are measured at:',
                'options' => [
                    'A' => 'Fair value of the equity instruments granted, measured at grant date',
                    'B' => 'Fair value of the equity instruments re-measured at each reporting date',
                    'C' => 'Intrinsically discounted book value of common shares on vesting date',
                    'D' => 'Cash equivalent compensation agreed upon in the employment contract',
                ],
                'correct' => 'A',
                'difficulty' => 'Hard',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PFRS 2 paragraph 10 states that for transactions with employees, the entity shall measure the fair value of the equity instruments granted at grant date, without subsequent re-measurement for equity-settled awards.',
            ],
            [
                'text' => 'Under PFRS 13 (Fair Value Measurement), which level in the fair value hierarchy represents unadjusted quoted prices in active markets for identical assets or liabilities?',
                'options' => [
                    'A' => 'Level 1 inputs',
                    'B' => 'Level 2 inputs',
                    'C' => 'Level 3 inputs',
                    'D' => 'Level 4 inputs',
                ],
                'correct' => 'A',
                'difficulty' => 'Easy',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PFRS 13 establishes a 3-level hierarchy: Level 1 inputs are quoted prices (unadjusted) in active markets for identical assets/liabilities that the entity can access at the measurement date.',
            ],
            [
                'text' => 'In a business combination accounted for under PFRS 3, if the acquirer interest in the net fair value of the acquiree identifiable assets and liabilities exceeds the consideration transferred, the excess is:',
                'options' => [
                    'A' => 'Recognized in profit or loss on the acquisition date as a gain on bargain purchase',
                    'B' => 'Recognized as negative goodwill in the asset section of the balance sheet',
                    'C' => 'Credited directly to share premium capital reserve',
                    'D' => 'Deferred and amortized over a maximum period not exceeding 20 years',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'PFRS 3 paragraph 34 requires that after reassessing whether all acquired assets and assumed liabilities have been correctly identified, any remaining excess is recognized in profit or loss as a gain on a bargain purchase on the acquisition date.',
            ],
            [
                'text' => 'Under PFRS 8 (Operating Segments), an operating segment is considered reportable if its reported revenue (internal and external) is at least what percentage of the combined revenue of all operating segments?',
                'options' => [
                    'A' => '10%',
                    'B' => '5%',
                    'C' => '20%',
                    'D' => '75%',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Financial Accounting and Reporting',
                'explanation' => 'Under PFRS 8 paragraph 13, an operating segment is reportable if it meets any of the 10% quantitative thresholds: 10% or more of combined revenue, 10% or more of combined profit/loss, or 10% or more of combined assets. (75% is the overall external revenue threshold).',
            ],
            [
                'text' => 'Under Philippine Standard on Quality Management 1 (PSQM 1), audit firms must design, implement, and operate a system of quality management that addresses:',
                'options' => [
                    'A' => 'Eight interrelated components including governance, ethics, engagement performance, and monitoring',
                    'B' => 'Maximizing billable audit fee margins across private university clients',
                    'C' => 'Automating tax filing submissions to the Bureau of Internal Revenue',
                    'D' => 'Restricting audit staff recruitment exclusively to top 10 CPA board placers',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSQM 1 establishes a robust risk-based approach requiring audit firms to address 8 components: governance and leadership, relevant ethical requirements, acceptance and continuance, engagement performance, resources, information and communication, risk assessment process, and monitoring and remediation.',
            ],
            [
                'text' => 'Under PSA 250 (Consideration of Laws and Regulations), if the auditor identifies non-compliance with laws and regulations (NOCLAR) by client management, the auditor should first:',
                'options' => [
                    'A' => 'Obtain an understanding of the nature of the act and the circumstances in which it occurred, and discuss with appropriate management and governance',
                    'B' => 'Immediately issue a press release disclosing the suspected violations',
                    'C' => 'Resign from the engagement without documenting the matter in workpapers',
                    'D' => 'File criminal charges directly with the regional trial court without audit committee notification',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 250 paragraph 19 requires that when the auditor becomes aware of information concerning an instance of non-compliance, the auditor shall obtain an understanding of the nature of the act and evaluate the potential effect on the financial statements, discussing with appropriate levels of management and those charged with governance.',
            ],
            [
                'text' => 'In audit sampling under PSA 530, the maximum monetary error in a population that the auditor is willing to accept is referred to as the:',
                'options' => [
                    'A' => 'Tolerable misstatement',
                    'B' => 'Expected misstatement',
                    'C' => 'Anomalous error threshold',
                    'D' => 'Alpha risk coefficient',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 530 paragraph 5 defines tolerable misstatement as a monetary amount set by the auditor in respect of which the auditor seeks to obtain an appropriate level of assurance that the monetary amount set by the auditor is not exceeded by the actual misstatement in the population.',
            ],
            [
                'text' => 'Under PSA 580 (Written Representations), if management refuses to provide the written representations requested by the auditor regarding its responsibility for financial statement preparation, the auditor shall:',
                'options' => [
                    'A' => 'Disclaimer of opinion or withdraw from the engagement where permitted by law',
                    'B' => 'Express an unmodified opinion with an other-matter explanation',
                    'C' => 'Sign the representation letter on management\'s behalf based on verbal inquiries',
                    'D' => 'Rely solely on prior year representations if retained earnings did not change',
                ],
                'correct' => 'A',
                'difficulty' => 'Hard',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 580 paragraph 20 states that if management does not provide one or more of the required written representations, the auditor shall disclaim an opinion on the financial statements or withdraw from the engagement when permissible.',
            ],
            [
                'text' => 'Under PSA 705, how does the auditor distinguish between a Qualified Opinion and an Adverse Opinion regarding material misstatement?',
                'options' => [
                    'A' => 'A Qualified Opinion is issued when misstatements are material but NOT pervasive; an Adverse Opinion is issued when misstatements are BOTH material and pervasive',
                    'B' => 'A Qualified Opinion relates to fraud; an Adverse Opinion relates strictly to errors',
                    'C' => 'A Qualified Opinion is chosen if client fees have been collected; Adverse if fees are outstanding',
                    'D' => 'Both opinions carry identical wording with different title headers',
                ],
                'correct' => 'A',
                'difficulty' => 'Normal',
                'domain' => 'Auditing and Assurance',
                'explanation' => 'PSA 705 clearly delineates: if misstatements are material but not pervasive, the auditor expresses a Qualified Opinion ("except for..."). If misstatements are both material and pervasive to the financial statements, the auditor must express an Adverse Opinion.',
            ],
        ];
    }

    /**
     * Seed second mock board with realistic at-risk attempt for Juan Dela Cruz (23-9991).
     */
    public function seedSecondMockBoard(User $teacher, ClassModel $class, array $students, ?User $adminUser): void
    {
        // 1. Create or update Mock Board
        $mockBoard = MockBoard::updateOrCreate(
            [
                'title' => '2026 CPALE Pre-Board Diagnostic & Simulated Examination',
                'class_id' => $class->id,
            ],
            [
                'description' => 'Comprehensive Mock Board Examination covering Financial Accounting and Reporting (FAR) and Auditing and Assurance for BSA graduating candidates.',
                'program' => 'accountancy',
                'teacher_id' => $teacher->id,
                'passing_percentage' => 75,
                'review_period_start' => now()->subDays(10)->toDateString(),
                'review_period_end' => now()->addDays(60)->toDateString(),
                'visibility' => 'all',
                'status' => 'approved',
                'approved_by' => $adminUser?->id ?? $teacher->id,
                'approved_at' => now(),
            ]
        );

        // 2. Phase 1 (Pre-Test Module)
        $preTestModule = Module::updateOrCreate(
            [
                'class_id' => $class->id,
                'title' => '2026 CPALE Pre-Board Diagnostic & Simulated Examination - Pre-Test',
            ],
            [
                'created_by' => $teacher->id,
                'description' => 'Baseline diagnostic examination prior to comprehensive review.',
                'is_quiz' => true,
                'is_formal_assessment' => true,
                'is_mock_board' => true,
                'passing_grade' => 75,
                'is_active' => true,
                'time_limit' => 60,
            ]
        );

        $preTestPhase = MockBoardPhase::updateOrCreate(
            [
                'mock_board_id' => $mockBoard->id,
                'phase_type' => 'pre_test',
            ],
            [
                'sequence_number' => 1,
                'label' => 'Pre-Test',
                'title' => 'Pre-Test Diagnostic Phase',
                'module_id' => $preTestModule->id,
            ]
        );

        $preQuestionsData = $this->getSecondMockBoardPreQuestions();
        $preQuestions = [];
        foreach ($preQuestionsData as $idx => $qd) {
            $preQuestions[] = QuizQuestion::updateOrCreate(
                [
                    'module_id' => $preTestModule->id,
                    'order' => $idx + 1,
                ],
                [
                    'question_text' => $qd['question_text'],
                    'options' => $qd['options'],
                    'correct_option' => $qd['correct_option'],
                    'points' => 1,
                    'difficulty' => $qd['difficulty'],
                    'domain' => $qd['domain'],
                    'explanation' => $qd['explanation'],
                ]
            );
        }

        // 3. Phase 2 (Pre-Boards Module)
        $postTestModule = Module::updateOrCreate(
            [
                'class_id' => $class->id,
                'title' => '2026 CPALE Pre-Board Diagnostic & Simulated Examination - Pre-Boards',
            ],
            [
                'created_by' => $teacher->id,
                'description' => 'Comprehensive simulated licensure examination across core CPALE subjects.',
                'is_quiz' => true,
                'is_formal_assessment' => true,
                'is_mock_board' => true,
                'passing_grade' => 75,
                'is_active' => true,
                'time_limit' => 180,
            ]
        );

        $postTestPhase = MockBoardPhase::updateOrCreate(
            [
                'mock_board_id' => $mockBoard->id,
                'phase_type' => 'pre_boards',
            ],
            [
                'sequence_number' => 1,
                'label' => 'Pre-Boards',
                'title' => 'Final Pre-Boards Licensure Simulation Phase',
                'module_id' => $postTestModule->id,
            ]
        );

        $postQuestionsData = $this->getSecondMockBoardPostQuestions();
        $postQuestions = [];
        foreach ($postQuestionsData as $idx => $qd) {
            $postQuestions[] = QuizQuestion::updateOrCreate(
                [
                    'module_id' => $postTestModule->id,
                    'order' => $idx + 1,
                ],
                [
                    'question_text' => $qd['question_text'],
                    'options' => $qd['options'],
                    'correct_option' => $qd['correct_option'],
                    'points' => 1,
                    'difficulty' => $qd['difficulty'],
                    'domain' => $qd['domain'],
                    'explanation' => $qd['explanation'],
                ]
            );
        }

        // 4. Populate Student Attempts (Remaining 9 classmates; 23-9991 answers manually)
        $studentsConfig = [
            '23-9992' => [
                'pre_correct' => [0, 1, 2, 3, 5, 6, 7, 8],
                'post_correct' => [0, 1, 2, 3, 4, 5, 6, 7, 8],
            ],
            '23-9993' => [
                'pre_correct' => [0, 1, 2, 5, 6, 7, 8],
                'post_correct' => [0, 1, 2, 3, 5, 6, 7, 8],
            ],
            '23-9994' => [
                'pre_correct' => [0, 1, 2, 5, 6, 7],
                'post_correct' => [0, 1, 2, 4, 5, 6, 7, 8],
            ],
            '23-9995' => [
                'pre_correct' => [0, 1, 2, 5, 6, 7],
                'post_correct' => [0, 1, 2, 5, 6, 7, 8],
            ],
            '23-9996' => [
                'pre_correct' => [0, 1, 2, 3, 5, 6, 7],
                'post_correct' => [0, 1, 2, 3, 4, 5, 6, 7, 9],
            ],
            '23-9997' => [
                'pre_correct' => [0, 1, 2, 5, 6, 7],
                'post_correct' => [0, 1, 2, 3, 5, 6, 7, 9],
            ],
            '23-9998' => [
                'pre_correct' => [0, 1, 2, 5, 6],
                'post_correct' => [0, 1, 2, 5, 6, 7],
            ],
            '23-10000' => [
                'pre_correct' => [0, 1, 5, 6, 7],
                'post_correct' => [0, 1, 3, 5, 6, 8],
            ],
            '23-10001' => [
                'pre_correct' => [0, 1, 2, 3, 5, 6, 7, 8],
                'post_correct' => [0, 1, 2, 3, 4, 5, 6, 7, 8],
            ],
        ];

        foreach ($studentsConfig as $idnumber => $cfg) {
            $student = User::where('idnumber', $idnumber)->first();
            if (! $student) {
                continue;
            }

            // Pre-Test Attempt
            $preQuizAttempt = QuizAttempt::updateOrCreate(
                [
                    'user_id' => $student->id,
                    'module_id' => $preTestModule->id,
                ],
                [
                    'quiz_stage' => 'pre_test',
                    'mock_board_id' => $mockBoard->id,
                    'mock_board_phase_type' => 'pre_test',
                    'score' => count($cfg['pre_correct']),
                    'total' => 10,
                    'percentage' => round((count($cfg['pre_correct']) / 10) * 100, 1),
                    'passed' => (count($cfg['pre_correct']) >= 8),
                    'status' => 'completed',
                    'created_at' => now()->subDays(3),
                    'updated_at' => now()->subDays(3),
                    'attempted_at' => now()->subDays(3),
                    'completed_at' => now()->subDays(3),
                ]
            );

            QuizAnswer::where('attempt_id', $preQuizAttempt->id)->delete();
            foreach ($preQuestions as $qIdx => $qObj) {
                $isCorrect = in_array($qIdx, $cfg['pre_correct'], true);
                QuizAnswer::create([
                    'attempt_id' => $preQuizAttempt->id,
                    'question_id' => $qObj->id,
                    'selected_option' => $isCorrect ? $qObj->correct_option : ($qObj->correct_option === 'A' ? 'B' : 'A'),
                    'is_correct' => $isCorrect,
                ]);
            }

            MockBoardAttempt::updateOrCreate(
                [
                    'user_id' => $student->id,
                    'mock_board_id' => $mockBoard->id,
                    'mock_board_phase_id' => $preTestPhase->id,
                ],
                [
                    'phase_type' => 'pre_test',
                    'quiz_attempt_id' => $preQuizAttempt->id,
                    'score' => $preQuizAttempt->score,
                    'total' => 10,
                    'percentage' => $preQuizAttempt->percentage,
                    'passed' => $preQuizAttempt->passed,
                    'attempt_count' => 1,
                    'created_at' => now()->subDays(3),
                    'updated_at' => now()->subDays(3),
                ]
            );

            // Post-Test Attempt
            $postQuizAttempt = QuizAttempt::updateOrCreate(
                [
                    'user_id' => $student->id,
                    'module_id' => $postTestModule->id,
                ],
                [
                    'quiz_stage' => 'post_test',
                    'mock_board_id' => $mockBoard->id,
                    'mock_board_phase_type' => 'pre_boards',
                    'score' => count($cfg['post_correct']),
                    'total' => 10,
                    'percentage' => round((count($cfg['post_correct']) / 10) * 100, 1),
                    'passed' => (count($cfg['post_correct']) >= 8),
                    'status' => 'completed',
                    'created_at' => now()->subDays(1),
                    'updated_at' => now()->subDays(1),
                    'attempted_at' => now()->subDays(1),
                    'completed_at' => now()->subDays(1),
                ]
            );

            QuizAnswer::where('attempt_id', $postQuizAttempt->id)->delete();
            foreach ($postQuestions as $qIdx => $qObj) {
                $isCorrect = in_array($qIdx, $cfg['post_correct'], true);
                QuizAnswer::create([
                    'attempt_id' => $postQuizAttempt->id,
                    'question_id' => $qObj->id,
                    'selected_option' => $isCorrect ? $qObj->correct_option : ($qObj->correct_option === 'A' ? 'B' : 'A'),
                    'is_correct' => $isCorrect,
                ]);
            }

            MockBoardAttempt::updateOrCreate(
                [
                    'user_id' => $student->id,
                    'mock_board_id' => $mockBoard->id,
                    'mock_board_phase_id' => $postTestPhase->id,
                ],
                [
                    'phase_type' => 'pre_boards',
                    'quiz_attempt_id' => $postQuizAttempt->id,
                    'score' => $postQuizAttempt->score,
                    'total' => 10,
                    'percentage' => $postQuizAttempt->percentage,
                    'passed' => $postQuizAttempt->passed,
                    'attempt_count' => 1,
                    'created_at' => now()->subDays(1),
                    'updated_at' => now()->subDays(1),
                ]
            );
        }

        try {
            app(MockBoardStatisticsService::class)->computeClassStatistics($mockBoard);
        } catch (\Throwable $e) {
            // Ignore if calculation occurs during UI view
        }
    }

    private function getSecondMockBoardPreQuestions(): array
    {
        return [
            [
                'question_text' => 'Under PAS 16, when an item of property, plant and equipment is revalued, any accumulated depreciation at the revaluation date may be treated by:',
                'options' => [
                    'A' => 'Restating proportionately with the change in the gross carrying amount, or eliminating against the gross carrying amount',
                    'B' => 'Debiting directly to retained earnings without affecting revaluation surplus',
                    'C' => 'Crediting immediately to current profit or loss as an extraordinary recovery',
                    'D' => 'Capitalizing into the initial historical cost of replacing assets',
                ],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'difficulty' => 'Medium',
                'explanation' => 'Under PAS 16 paragraph 35, accumulated depreciation is either restated proportionately with the change in the gross carrying amount or eliminated against the gross carrying amount.',
            ],
            [
                'question_text' => 'According to PFRS 9, a financial asset is measured at amortized cost if which of the following two conditions are met?',
                'options' => [
                    'A' => 'Held within a business model to collect contractual cash flows, and cash flows are solely payments of principal and interest (SPPI)',
                    'B' => 'Held primarily for trading, and designated irrevocably at fair value through profit or loss',
                    'C' => 'Quoted in an active exchange market, and backed by government securities',
                    'D' => 'Matures within 12 months, and carries an investment-grade rating',
                ],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'difficulty' => 'Medium',
                'explanation' => 'PFRS 9 paragraph 4.1.2 requires both the business model test (collect cash flows) and the contractual cash flow characteristics test (SPPI) to measure financial assets at amortized cost.',
            ],
            [
                'question_text' => 'Under PFRS 15, which step in the five-step revenue recognition model involves determining whether distinct goods or services are promised?',
                'options' => [
                    'A' => 'Step 2: Identify the performance obligations in the contract',
                    'B' => 'Step 1: Identify the contract with a customer',
                    'C' => 'Step 3: Determine the transaction price',
                    'D' => 'Step 4: Allocate the transaction price to performance obligations',
                ],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'difficulty' => 'Medium',
                'explanation' => 'Under PFRS 15, Step 2 requires evaluating promised goods and services to identify each distinct performance obligation.',
            ],
            [
                'question_text' => 'Under PAS 36, an impairment loss is recognized when the carrying amount of an asset exceeds its recoverable amount. The recoverable amount is defined as:',
                'options' => [
                    'A' => 'The higher of fair value less costs of disposal and value in use',
                    'B' => 'The lower of replacement cost and net realizable value',
                    'C' => 'The discounted present value of past operational cash inflows',
                    'D' => 'The liquidation value assessed by external auctioneers',
                ],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'difficulty' => 'Medium',
                'explanation' => 'PAS 36 paragraph 18 states recoverable amount is the higher of an asset\'s or cash-generating unit\'s fair value less costs of disposal and its value in use.',
            ],
            [
                'question_text' => 'Under PAS 19, remeasurements of the net defined benefit liability (asset), which include actuarial gains and losses, are recognized in:',
                'options' => [
                    'A' => 'Other comprehensive income and never reclassified to profit or loss in subsequent periods',
                    'B' => 'Profit or loss over the expected remaining working lives of participating employees',
                    'C' => 'Current operating expenses as an adjustment to service cost',
                    'D' => 'Deferred expense asset amortized over ten years',
                ],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'difficulty' => 'Medium',
                'explanation' => 'PAS 19 requires actuarial remeasurements to be recognized immediately in Other Comprehensive Income (OCI) with no subsequent recycling to profit or loss.',
            ],
            [
                'question_text' => 'Under PSA 200, the overall objective of the auditor is to obtain reasonable assurance that the financial statements as a whole are free from material misstatement. Reasonable assurance represents:',
                'options' => [
                    'A' => 'A high, but not absolute, level of assurance due to inherent limitations of an audit',
                    'B' => 'An absolute guarantee that fraud has been completely detected',
                    'C' => 'A moderate level of assurance equivalent to a review engagement',
                    'D' => 'A mathematical probability of exactly 95% confidence',
                ],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'difficulty' => 'Medium',
                'explanation' => 'PSA 200 paragraph 5 defines reasonable assurance as a high level of assurance, but not an absolute guarantee due to inherent limitations of an audit.',
            ],
            [
                'question_text' => 'Under PSA 315, why must an auditor obtain an understanding of internal control relevant to the audit?',
                'options' => [
                    'A' => 'To identify types of potential misstatements and assess the risks of material misstatement at the assertion level',
                    'B' => 'To provide a separate legal certification on management efficiency',
                    'C' => 'To eliminate the need for substantive analytical procedures',
                    'D' => 'To prepare accounting vouchers on behalf of the client controller',
                ],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'difficulty' => 'Medium',
                'explanation' => 'PSA 315 paragraph 12 requires understanding internal control to identify potential misstatements, consider factors that affect RMM, and design responsive audit procedures.',
            ],
            [
                'question_text' => 'Under PSA 330, substantive procedures consist of tests of details and:',
                'options' => [
                    'A' => 'Substantive analytical procedures',
                    'B' => 'Tests of internal control design',
                    'C' => 'Walkthrough inquiries with suppliers only',
                    'D' => 'Interviews with labor union leaders',
                ],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'difficulty' => 'Medium',
                'explanation' => 'PSA 330 paragraph 4 defines substantive procedures as procedures designed to detect material misstatements at the assertion level, comprising tests of details and substantive analytical procedures.',
            ],
            [
                'question_text' => 'Under PSA 500, which of the following types of audit evidence is generally considered most reliable?',
                'options' => [
                    'A' => 'Original documents obtained directly from independent external sources',
                    'B' => 'Oral explanations provided by client accounts receivable clerks',
                    'C' => 'Internal copies of sales invoices prepared by the client billing department',
                    'D' => 'Draft photocopies of board resolutions without signature',
                ],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'difficulty' => 'Medium',
                'explanation' => 'PSA 500 paragraph A31 notes that audit evidence obtained directly from knowledgeable external independent sources in original documentary form is more reliable than internal evidence.',
            ],
            [
                'question_text' => 'Under PSA 700, what section in the auditor\'s report explains the respective responsibilities of management and those charged with governance?',
                'options' => [
                    'A' => 'Responsibilities of Management and Those Charged with Governance for the Financial Statements',
                    'B' => 'Basis for Opinion paragraph',
                    'C' => 'Key Audit Matters overview section',
                    'D' => 'Emphasis of Matter disclosure note',
                ],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'difficulty' => 'Medium',
                'explanation' => 'PSA 700 paragraph 33-36 requires a dedicated section detailing the responsibilities of management and governance for preparing financial statements and internal controls.',
            ],
        ];
    }

    private function getSecondMockBoardPostQuestions(): array
    {
        return [
            [
                'question_text' => 'Under PFRS 16, a lessee measures the lease liability at the commencement date at the present value of lease payments discounted using:',
                'options' => [
                    'A' => 'The interest rate implicit in the lease, or if not readily determinable, the lessee\'s incremental borrowing rate',
                    'B' => 'The prime lending rate published by the Bangko Sentral ng Pilipinas regardless of contract terms',
                    'C' => 'The risk-free rate of 10-year Philippine Treasury bonds plus 2%',
                    'D' => 'The historical dividend yield of the lessor corporation',
                ],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'difficulty' => 'Hard',
                'explanation' => 'Under PFRS 16 paragraph 26, the lease liability is discounted using the rate implicit in the lease or, if not determinable, the lessee\'s incremental borrowing rate.',
            ],
            [
                'question_text' => 'Under PAS 12, a deferred tax asset is recognized for deductible temporary differences, unused tax losses, and tax credits to the extent that:',
                'options' => [
                    'A' => 'It is probable that future taxable profit will be available against which they can be utilized',
                    'B' => 'The entity has existed for more than five operational calendar years',
                    'C' => 'Current year revenue exceeds audited total operating expenses by at least 15%',
                    'D' => 'Approved by the Bureau of Internal Revenue through a formal tax ruling',
                ],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'difficulty' => 'Hard',
                'explanation' => 'PAS 12 paragraph 24 mandates recognition of deferred tax assets only when it is probable that taxable profit will be available against which deductible temporary differences can be utilized.',
            ],
            [
                'question_text' => 'Under PFRS 10, an investor controls an investee if and only if the investor possesses power over the investee, exposure or rights to variable returns, and:',
                'options' => [
                    'A' => 'The ability to use its power over the investee to affect the amount of the investor\'s returns',
                    'B' => 'Ownership of at least 75% of the voting common shares of the investee',
                    'C' => 'A contractual agreement to guarantee all outstanding corporate liabilities of the investee',
                    'D' => 'Approval from the Securities and Exchange Commission to consolidate financial statements',
                ],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'difficulty' => 'Hard',
                'explanation' => 'PFRS 10 paragraph 7 sets out the three cumulative elements of control: power, exposure to variable returns, and linkage between power and returns.',
            ],
            [
                'question_text' => 'Under PAS 37, a provision is recognized when an entity has a present legal or constructive obligation from a past event, it is probable that an outflow of resources will be required, and:',
                'options' => [
                    'A' => 'A reliable estimate can be made of the amount of the obligation',
                    'B' => 'A formal legal lawsuit has been docketed in a Philippine court of law',
                    'C' => 'Management has secured insurance indemnity covering 100% of the claim',
                    'D' => 'The board of directors has authorized cash disbursement in the subsequent annual budget',
                ],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'difficulty' => 'Hard',
                'explanation' => 'PAS 37 paragraph 14 requires three criteria for provision recognition: present obligation, probable outflow (>50%), and reliable estimate.',
            ],
            [
                'question_text' => 'Under PAS 38, expenditure on research (or the research phase of an internal project) must be:',
                'options' => [
                    'A' => 'Recognized as an expense when it is incurred',
                    'B' => 'Capitalized as an intangible asset and amortized over 20 years',
                    'C' => 'Recorded in other comprehensive income until technical feasibility is proven',
                    'D' => 'Deferred on the balance sheet as unamortized pre-operating project cost',
                ],
                'correct_option' => 'A',
                'domain' => 'Financial Accounting and Reporting',
                'difficulty' => 'Hard',
                'explanation' => 'PAS 38 paragraph 54 strictly mandates that no intangible asset arising from research shall be recognized; research costs must be expensed as incurred.',
            ],
            [
                'question_text' => 'Under PSA 240, what are the three conditions generally present when material misstatements due to fraud occur (the Fraud Triangle)?',
                'options' => [
                    'A' => 'Incentive/pressure, opportunity, and rationalization/attitude',
                    'B' => 'Collusion, bribery, and computerized accounting omission',
                    'C' => 'Inadequate segregation of duties, cash deficit, and auditor negligence',
                    'D' => 'Overstated revenue, understated liabilities, and fictitious inventory counts',
                ],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'difficulty' => 'Hard',
                'explanation' => 'PSA 240 paragraph A1 identifies the three fraud risk conditions: incentive/pressure, perceived opportunity, and rationalization.',
            ],
            [
                'question_text' => 'Under PSA 520, which of the following is an essential requirement when designing and performing substantive analytical procedures?',
                'options' => [
                    'A' => 'Develop an expectation of recorded amounts or ratios and evaluate whether the expectation is sufficiently precise',
                    'B' => 'Limit analytical procedures exclusively to the balance sheet accounts at year-end',
                    'C' => 'Obtain written confirmation from all commercial bank branch managers',
                    'D' => 'Perform continuous surprise physical counts of fixed assets and warehouse inventories',
                ],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'difficulty' => 'Hard',
                'explanation' => 'PSA 520 paragraph 5(c) requires developing an expectation of recorded amounts and evaluating if it is sufficiently precise to identify misstatements.',
            ],
            [
                'question_text' => 'Under PSA 530, sampling risk is the risk that the auditor\'s conclusion based on a sample may be different from the conclusion if the entire population were subjected to the same audit procedure. Sampling risk can lead to two types of erroneous conclusions: risk of incorrect acceptance and:',
                'options' => [
                    'A' => 'Risk of incorrect rejection (affecting audit efficiency)',
                    'B' => 'Risk of procedural disqualification by the Professional Regulation Commission',
                    'C' => 'Risk of statistical kurtosis exceeding acceptable standard deviations',
                    'D' => 'Risk of contract termination by the corporate audit committee',
                ],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'difficulty' => 'Hard',
                'explanation' => 'PSA 530 paragraph A11 defines sampling risk outcomes: risk of incorrect acceptance (affects effectiveness) and risk of incorrect rejection (affects efficiency).',
            ],
            [
                'question_text' => 'Under PSA 560, events occurring between the date of the financial statements and the date of the auditor\'s report that provide evidence of conditions that existed at the balance sheet date are:',
                'options' => [
                    'A' => 'Adjusting events (Type 1 events) requiring adjustments to amounts in the financial statements',
                    'B' => 'Non-adjusting events (Type 2 events) requiring disclosure notes only',
                    'C' => 'Inconsequential subsequent events that are excluded from audit workpapers',
                    'D' => 'Contingencies to be noted exclusively in the management representation letter',
                ],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'difficulty' => 'Hard',
                'explanation' => 'PSA 560 paragraph 5 defines adjusting events as those providing evidence of conditions that existed at the date of the financial statements requiring adjustment.',
            ],
            [
                'question_text' => 'Under PSA 705, an Adverse Opinion should be expressed when the auditor, having obtained sufficient appropriate audit evidence, concludes that misstatements are:',
                'options' => [
                    'A' => 'Both material and pervasive to the financial statements',
                    'B' => 'Material but NOT pervasive to the financial statements',
                    'C' => 'Confined strictly to related-party footnote disclosures',
                    'D' => 'Caused solely by sudden changes in national tax legislation',
                ],
                'correct_option' => 'A',
                'domain' => 'Auditing and Assurance',
                'difficulty' => 'Hard',
                'explanation' => 'PSA 705 paragraph 8 states that the auditor shall express an adverse opinion when misstatements are BOTH material and pervasive to the financial statements.',
            ],
        ];
    }
}
