<?php

use App\Models\ClassModel;
use App\Models\MockBoard;
use App\Models\MockBoardAttempt;
use App\Models\MockBoardPhase;
use App\Models\Module;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\MockBoardStatisticsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to seed the new mock board exam with realistic student attempts.
     */
    public function up(): void
    {
        if (! Schema::hasTable('mock_boards') || ! Schema::hasTable('users')) {
            return;
        }

        DB::transaction(function () {
            // Find teacher and class for Accountancy
            $teacher = User::where('idnumber', '23-9999')->first()
                ?? User::where('role', 'teacher')->where('program', 'accountancy')->first();

            $class = ClassModel::where('code', 'ACC401-2026')->first()
                ?? ClassModel::where('program', 'accountancy')->first();

            if (! $teacher || ! $class) {
                return;
            }

            $admin = User::where('role', 'admin')->first();

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
                    'approved_by' => $admin?->id ?? $teacher->id,
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

            // 3. Pre-Test Questions
            $preQuestionsData = [
                // FAR
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
                // Auditing
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

            $preQuestions = [];
            foreach ($preQuestionsData as $idx => $qd) {
                $q = QuizQuestion::updateOrCreate(
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
                $preQuestions[] = $q;
            }

            // 4. Phase 2 (Pre-Boards Module)
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

            // 5. Pre-Boards Questions
            $postQuestionsData = [
                // FAR
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
                // Auditing
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

            $postQuestions = [];
            foreach ($postQuestionsData as $idx => $qd) {
                $q = QuizQuestion::updateOrCreate(
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
                $postQuestions[] = $q;
            }

            // 6. Student Attempts
            // Student 23-9991 (Juan Dela Cruz) gets LOW SCORE: 3/10 (30%) on Pre-Boards
            // Other 9 students get passing/developing scores (60% to 90%)
            $studentsConfig = [
                '23-9992' => [
                    'pre_correct' => [0, 1, 2, 3, 5, 6, 7, 8],
                    'post_correct' => [0, 1, 2, 3, 4, 5, 6, 7, 8], // 90%
                ],
                '23-9993' => [
                    'pre_correct' => [0, 1, 2, 5, 6, 7, 8],
                    'post_correct' => [0, 1, 2, 3, 5, 6, 7, 8], // 80%
                ],
                '23-9994' => [
                    'pre_correct' => [0, 1, 2, 5, 6, 7],
                    'post_correct' => [0, 1, 2, 4, 5, 6, 7, 8], // 80%
                ],
                '23-9995' => [
                    'pre_correct' => [0, 1, 2, 5, 6, 7],
                    'post_correct' => [0, 1, 2, 5, 6, 7, 8], // 70%
                ],
                '23-9996' => [
                    'pre_correct' => [0, 1, 2, 3, 5, 6, 7],
                    'post_correct' => [0, 1, 2, 3, 4, 5, 6, 7, 9], // 90%
                ],
                '23-9997' => [
                    'pre_correct' => [0, 1, 2, 5, 6, 7],
                    'post_correct' => [0, 1, 2, 3, 5, 6, 7, 9], // 80%
                ],
                '23-9998' => [
                    'pre_correct' => [0, 1, 2, 5, 6],
                    'post_correct' => [0, 1, 2, 5, 6, 7], // 60%
                ],
                '23-10000' => [
                    'pre_correct' => [0, 1, 5, 6, 7],
                    'post_correct' => [0, 1, 3, 5, 6, 8], // 60%
                ],
                '23-10001' => [
                    'pre_correct' => [0, 1, 2, 3, 5, 6, 7, 8],
                    'post_correct' => [0, 1, 2, 3, 4, 5, 6, 7, 8], // 90%
                ],
            ];

            foreach ($studentsConfig as $idnumber => $cfg) {
                $student = User::where('idnumber', $idnumber)->first();
                if (! $student) {
                    continue;
                }

                // A. Pre-Test Attempt
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

                // B. Post-Test (Pre-Boards) Attempt
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

            // 7. Compute class statistics
            try {
                app(MockBoardStatisticsService::class)->computeClassStatistics($mockBoard);
            } catch (Throwable $e) {
                // Ignore if computation succeeds on page load
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $mockBoard = MockBoard::where('title', '2026 CPALE Pre-Board Diagnostic & Simulated Examination')->first();
        if (! $mockBoard) {
            return;
        }

        $phaseIds = MockBoardPhase::where('mock_board_id', $mockBoard->id)->pluck('id');
        $moduleIds = MockBoardPhase::where('mock_board_id', $mockBoard->id)->pluck('module_id');

        $quizAttemptIds = QuizAttempt::where('mock_board_id', $mockBoard->id)
            ->orWhereIn('module_id', $moduleIds)
            ->pluck('id');

        QuizAnswer::whereIn('attempt_id', $quizAttemptIds)->delete();
        QuizAttempt::whereIn('id', $quizAttemptIds)->delete();
        MockBoardAttempt::where('mock_board_id', $mockBoard->id)->delete();
        MockBoardPhase::whereIn('id', $phaseIds)->delete();
        QuizQuestion::whereIn('module_id', $moduleIds)->delete();
        Module::whereIn('id', $moduleIds)->delete();
        $mockBoard->delete();
    }
};
