<?php

namespace App\Services;

use App\Models\MockBoard;
use App\Models\MockBoardAttempt;
use App\Models\MockBoardReadinessReport;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MockBoardReadinessService
{
    public function __construct(
        protected CloudflareAI $ai,
        protected AiSettingsResolver $aiSettings
    ) {}

    /**
     * Check if a student is eligible to view the readiness report.
     * Report is unlocked only after completing both the Pre-Test and at least one Post-Test (pre_boards).
     */
    public function canAccessReadiness(MockBoard $mockBoard, User $user): bool
    {
        $hasPreTest = MockBoardAttempt::where('user_id', $user->id)
            ->where('mock_board_id', $mockBoard->id)
            ->where('phase_type', 'pre_test')
            ->exists();

        $hasPostTest = MockBoardAttempt::where('user_id', $user->id)
            ->where('mock_board_id', $mockBoard->id)
            ->where('phase_type', 'pre_boards')
            ->exists();

        return $hasPreTest && $hasPostTest;
    }

    /**
     * Retrieve an existing cached readiness report or generate a fresh one if outdated.
     */
    public function getOrGenerateReport(MockBoard $mockBoard, User $user, bool $forceRegenerate = false): MockBoardReadinessReport
    {
        $existingReport = MockBoardReadinessReport::where('user_id', $user->id)
            ->where('mock_board_id', $mockBoard->id)
            ->first();

        $latestAttemptUpdatedAt = MockBoardAttempt::where('user_id', $user->id)
            ->where('mock_board_id', $mockBoard->id)
            ->max('updated_at');

        $isFresh = $existingReport
            && $existingReport->generated_at
            && $latestAttemptUpdatedAt
            && $existingReport->generated_at->gte($latestAttemptUpdatedAt);

        if (! $forceRegenerate && $isFresh) {
            return $existingReport;
        }

        $reportData = $this->buildReportData($mockBoard, $user);
        $actionPlan = $this->generateActionPlan($reportData);

        return MockBoardReadinessReport::updateOrCreate(
            [
                'user_id' => $user->id,
                'mock_board_id' => $mockBoard->id,
            ],
            [
                'readiness_percentage' => $reportData['summary']['readiness_percentage'],
                'tier' => $reportData['summary']['tier'],
                'tier_label' => $reportData['summary']['tier_label'],
                'gap_percentage' => $reportData['summary']['gap_percentage'],
                'gap_items' => $reportData['summary']['gap_items'],
                'pre_test_score' => $reportData['growth']['pre_test_score'],
                'post_test_score' => $reportData['growth']['post_test_score'],
                'improvement_percentage' => $reportData['growth']['improvement_percentage'],
                'consistency_status' => $reportData['growth']['consistency_status'],
                'domain_breakdown' => $reportData['domains'],
                'item_insights' => $reportData['item_insights'],
                'peer_benchmark' => $reportData['peer_benchmark'],
                'historical_comparison' => $reportData['historical_comparison'],
                'ai_action_plan' => $actionPlan,
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Compute comprehensive metrics, domain mastery, item insights, and peer benchmarks.
     *
     * @return array<string, mixed>
     */
    public function buildReportData(MockBoard $mockBoard, User $user): array
    {
        $threshold = (int) ($mockBoard->passing_percentage ?? 75);

        // 1. Fetch Student Attempts with related questions and answers
        $preTestAttempt = MockBoardAttempt::where('user_id', $user->id)
            ->where('mock_board_id', $mockBoard->id)
            ->where('phase_type', 'pre_test')
            ->with(['quizAttempt.answers.question', 'phase'])
            ->first();

        $postTestAttempts = MockBoardAttempt::where('user_id', $user->id)
            ->where('mock_board_id', $mockBoard->id)
            ->where('phase_type', 'pre_boards')
            ->with(['quizAttempt.answers.question', 'phase'])
            ->orderByDesc('percentage')
            ->get();

        $bestPostTest = $postTestAttempts->first();

        // 2. Section A: Readiness Summary
        $readinessScore = (float) ($bestPostTest?->percentage ?? 0);
        $tierInfo = MockBoardStatisticsService::calculateReadinessTier($readinessScore, $threshold);
        $gapPercentage = max(0.0, round($threshold - $readinessScore, 1));
        $totalItems = $bestPostTest?->total ?? 100;
        $gapItems = $gapPercentage > 0 ? (int) ceil(($gapPercentage / 100) * $totalItems) : 0;

        $summary = [
            'readiness_percentage' => $readinessScore,
            'passing_threshold' => $threshold,
            'tier' => $tierInfo['tier'],
            'tier_label' => $tierInfo['label'],
            'tier_icon' => $tierInfo['icon'],
            'tier_color' => $tierInfo['color'],
            'tier_badge_class' => $tierInfo['badge_class'],
            'tier_description' => $tierInfo['description'],
            'gap_percentage' => $gapPercentage,
            'gap_items' => $gapItems,
            'total_items' => $totalItems,
        ];

        // 3. Section B: Growth & Performance
        $preScore = $preTestAttempt ? (float) $preTestAttempt->percentage : 0.0;
        $improvement = round($readinessScore - $preScore, 1);

        $consistencyStatus = 'Single Attempt';
        if ($postTestAttempts->count() > 1) {
            $spread = $postTestAttempts->max('percentage') - $postTestAttempts->min('percentage');
            if ($spread <= 5) {
                $consistencyStatus = 'Consistent';
            } elseif ($spread <= 15) {
                $consistencyStatus = 'Moderately Consistent';
            } else {
                $consistencyStatus = 'Inconsistent';
            }
        }

        $attemptsHistory = $postTestAttempts->map(function ($att) {
            return [
                'phase_label' => $att->phase?->phase_label ?? 'Post-Test',
                'percentage' => (float) $att->percentage,
                'score' => (int) $att->score,
                'total' => (int) $att->total,
                'passed' => (bool) $att->passed,
                'date' => $att->created_at?->format('M d, Y'),
            ];
        })->values()->toArray();

        $growth = [
            'pre_test_score' => $preScore,
            'post_test_score' => $readinessScore,
            'improvement_percentage' => $improvement,
            'consistency_status' => $consistencyStatus,
            'post_tests_count' => $postTestAttempts->count(),
            'attempts_history' => $attemptsHistory,
        ];

        // 4. Section C: Domain / Subject Mastery
        $preAnswers = $preTestAttempt?->quizAttempt?->answers ?? collect();
        $postAnswers = $bestPostTest?->quizAttempt?->answers ?? collect();

        $domainsData = $this->computeDomainBreakdown($preAnswers, $postAnswers);

        // 5. Section D: Item-Level Insights (Repeatedly missed & Regressed items)
        $itemInsights = $this->computeItemInsights($preAnswers, $postAnswers);

        // 6. Peer Benchmark (Cohort Average & Percentile Rank)
        $peerBenchmark = $this->computePeerBenchmark($mockBoard, $readinessScore, $user);

        // 7. Historical Comparison (PRC Licensure Examination context)
        $historicalComparison = $this->computeHistoricalComparison($mockBoard, $threshold);

        return [
            'summary' => $summary,
            'growth' => $growth,
            'domains' => $domainsData,
            'item_insights' => $itemInsights,
            'peer_benchmark' => $peerBenchmark,
            'historical_comparison' => $historicalComparison,
            'program' => $mockBoard->program ?? $user->program ?? 'accountancy',
        ];
    }

    /**
     * Compute accuracy and growth per domain.
     *
     * @param  Collection  $preAnswers
     * @param  Collection  $postAnswers
     * @return array<string, mixed>
     */
    protected function computeDomainBreakdown($preAnswers, $postAnswers): array
    {
        $preMap = [];
        foreach ($preAnswers as $ans) {
            $domain = ! empty($ans->question?->domain) ? trim($ans->question->domain) : 'General';
            $preMap[$domain] ??= ['correct' => 0, 'total' => 0];
            $preMap[$domain]['total']++;
            if ($ans->is_correct) {
                $preMap[$domain]['correct']++;
            }
        }

        $postMap = [];
        foreach ($postAnswers as $ans) {
            $domain = ! empty($ans->question?->domain) ? trim($ans->question->domain) : 'General';
            $postMap[$domain] ??= ['correct' => 0, 'total' => 0];
            $postMap[$domain]['total']++;
            if ($ans->is_correct) {
                $postMap[$domain]['correct']++;
            }
        }

        $allDomains = array_unique(array_merge(array_keys($preMap), array_keys($postMap)));
        sort($allDomains);

        $breakdown = [];
        foreach ($allDomains as $domain) {
            $preTotal = $preMap[$domain]['total'] ?? 0;
            $preCorrect = $preMap[$domain]['correct'] ?? 0;
            $prePct = $preTotal > 0 ? round(($preCorrect / $preTotal) * 100, 1) : null;

            $postTotal = $postMap[$domain]['total'] ?? 0;
            $postCorrect = $postMap[$domain]['correct'] ?? 0;
            $postPct = $postTotal > 0 ? round(($postCorrect / $postTotal) * 100, 1) : 0.0;

            $change = $prePct !== null ? round($postPct - $prePct, 1) : null;

            $status = 'Weak';
            $statusClass = 'bg-rose-100 text-rose-800 border-rose-300';
            if ($postPct >= 75) {
                $status = 'Strong';
                $statusClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
            } elseif ($postPct >= 60) {
                $status = 'Developing';
                $statusClass = 'bg-amber-100 text-amber-800 border-amber-300';
            }

            $breakdown[] = [
                'domain' => $domain,
                'pre_score' => $prePct,
                'post_score' => $postPct,
                'change' => $change,
                'status' => $status,
                'status_class' => $statusClass,
                'correct_items' => $postCorrect,
                'total_items' => $postTotal,
            ];
        }

        $sortedByPost = collect($breakdown)->sortBy('post_score')->values();

        // Priority Focus Area (weakest domain): only assign if there is a domain below 100%
        $domainsBelow100 = $sortedByPost->filter(fn ($d) => ($d['post_score'] ?? 0) < 100)->values();
        $weakestDomain = $domainsBelow100->isNotEmpty() ? $domainsBelow100->first()['domain'] : null;

        $strongestDomain = $sortedByPost->last()['domain'] ?? 'N/A';

        // Highest Improvement: only assign if change is positive (> 0)
        $sortedByGrowth = collect($breakdown)->filter(fn ($d) => $d['change'] !== null && $d['change'] > 0)->sortByDesc('change')->values();
        $mostImprovedDomain = $sortedByGrowth->first()['domain'] ?? null;

        return [
            'list' => $breakdown,
            'weakest_domain' => $weakestDomain,
            'strongest_domain' => $strongestDomain,
            'most_improved_domain' => $mostImprovedDomain,
        ];
    }

    /**
     * Compute item level regressions and repeatedly missed concepts.
     *
     * @param  Collection  $preAnswers
     * @param  Collection  $postAnswers
     * @return array<string, mixed>
     */
    protected function computeItemInsights($preAnswers, $postAnswers): array
    {
        $preByQuestionId = $preAnswers->keyBy('question_id');
        $preByQuestionText = $preAnswers->keyBy(fn ($a) => trim((string) $a->question?->question_text));

        $regressedItems = [];
        $repeatedlyMissed = [];
        $missedPostItems = [];

        foreach ($postAnswers as $postAns) {
            $question = $postAns->question;
            if (! $question) {
                continue;
            }

            if (! $postAns->is_correct) {
                $itemData = [
                    'question_id' => $question->id,
                    'stem' => Str::limit(trim($question->question_text ?? ''), 90),
                    'domain' => ! empty($question->domain) ? trim($question->domain) : 'General',
                    'explanation' => ! empty($question->explanation) ? Str::limit(trim($question->explanation), 120) : null,
                ];
                if (count($missedPostItems) < 6) {
                    $missedPostItems[] = $itemData;
                }
            }

            $preAns = $preByQuestionId->get($question->id)
                ?? $preByQuestionText->get(trim((string) $question->question_text));

            if (! $preAns) {
                continue;
            }

            $itemData = [
                'question_id' => $question->id,
                'stem' => Str::limit(trim($question->question_text), 90),
                'domain' => ! empty($question->domain) ? trim($question->domain) : 'General',
                'explanation' => ! empty($question->explanation) ? Str::limit(trim($question->explanation), 120) : null,
            ];

            if ($preAns->is_correct && ! $postAns->is_correct) {
                if (count($regressedItems) < 5) {
                    $regressedItems[] = $itemData;
                }
            } elseif (! $preAns->is_correct && ! $postAns->is_correct) {
                if (count($repeatedlyMissed) < 5) {
                    $repeatedlyMissed[] = $itemData;
                }
            }
        }

        return [
            'regressed_items' => $regressedItems,
            'repeatedly_missed_items' => $repeatedlyMissed,
            'missed_post_items' => $missedPostItems,
            'regressed_count' => count($regressedItems),
            'repeatedly_missed_count' => count($repeatedlyMissed),
        ];
    }

    /**
     * Compute cohort peer benchmarks: batch average, student percentile, and standing.
     *
     * @return array<string, mixed>
     */
    protected function computePeerBenchmark(MockBoard $mockBoard, float $studentScore, User $student): array
    {
        $allStudentScores = MockBoardAttempt::where('mock_board_id', $mockBoard->id)
            ->where('phase_type', 'pre_boards')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($attempts) => (float) $attempts->max('percentage'))
            ->values();

        $cohortSize = $allStudentScores->count();
        $batchAverage = $cohortSize > 0 ? round($allStudentScores->avg(), 1) : 0.0;

        $belowCount = $allStudentScores->filter(fn ($score) => $score < $studentScore)->count();
        $higherThanPercentage = $cohortSize > 1
            ? (int) round(($belowCount / ($cohortSize - 1)) * 100)
            : 100;

        $percentileRank = $cohortSize > 0
            ? (int) round(($belowCount / $cohortSize) * 100)
            : 0;

        $scoreDiffFromBatch = round($studentScore - $batchAverage, 1);

        return [
            'cohort_size' => $cohortSize,
            'batch_average' => $batchAverage,
            'student_score' => $studentScore,
            'score_diff_from_batch' => $scoreDiffDiff = $scoreDiffFromBatch,
            'higher_than_percentage' => $higherThanPercentage,
            'percentile_rank' => $percentileRank,
        ];
    }

    /**
     * Compute historical licensure examination benchmarks if linked.
     *
     * @return array<string, mixed>|null
     */
    protected function computeHistoricalComparison(MockBoard $mockBoard, int $threshold): ?array
    {
        if (! $mockBoard->historical_board_exam_result_id) {
            return null;
        }

        $mockBoard->loadMissing('historicalBoardExamResult');
        $historical = $mockBoard->historicalBoardExamResult;
        if (! $historical) {
            return null;
        }

        $cohortScores = MockBoardAttempt::where('mock_board_id', $mockBoard->id)
            ->where('phase_type', 'pre_boards')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($attempts) => (float) $attempts->max('percentage'))
            ->values();

        $totalTakers = $cohortScores->count();
        $passedCount = $cohortScores->filter(fn ($s) => $s >= $threshold)->count();
        $batchPassingRate = $totalTakers > 0 ? round(($passedCount / $totalTakers) * 100, 1) : 0.0;

        return [
            'exam_label' => $historical->exam_label,
            'exam_period_or_year' => $historical->exam_period_or_year,
            'national_passing_rate' => $historical->passing_rate,
            'national_total_examinees' => $historical->total_examinees,
            'national_passed_count' => $historical->passed_count,
            'batch_passing_rate' => $batchPassingRate,
            'source_note' => $historical->source_note,
        ];
    }

    /**
     * Generate personalized AI action plan with deterministic fallback.
     *
     * @param  array<string, mixed>  $reportData
     * @return array{priority_domains: array<string>, review_topics: array<string>, study_steps: array<string>, summary_narrative: string}
     */
    public function generateActionPlan(array $reportData): array
    {
        $fallback = $this->buildFallbackActionPlan($reportData);

        try {
            $readiness = (float) ($reportData['summary']['readiness_percentage'] ?? 0);
            $domainList = (array) ($reportData['domains']['list'] ?? []);
            $allDomains100 = ! empty($domainList) && collect($domainList)->every(fn ($d) => ($d['post_score'] ?? 0) >= 100);
            $isPerfectScore = $readiness >= 100 || $allDomains100;

            $weakestDomains = collect($domainList)
                ->where('status', 'Weak')
                ->pluck('domain')
                ->take(3)
                ->toArray();

            if (empty($weakestDomains)) {
                $weakestDomains = collect($domainList)
                    ->filter(fn ($d) => ($d['post_score'] ?? 0) < 100)
                    ->sortBy('post_score')
                    ->pluck('domain')
                    ->take(2)
                    ->toArray();
            }

            $missedStems = array_merge(
                collect($reportData['item_insights']['repeatedly_missed_items'] ?? [])->pluck('stem')->toArray(),
                collect($reportData['item_insights']['regressed_items'] ?? [])->pluck('stem')->toArray(),
                collect($reportData['item_insights']['missed_post_items'] ?? [])->pluck('stem')->toArray()
            );
            $missedStems = array_values(array_unique($missedStems));

            // If the student has a perfect score or no weak domains and no missed items, use the dedicated mastery fallback plan directly
            if ($isPerfectScore || (empty($weakestDomains) && empty($missedStems))) {
                return $fallback;
            }

            $testedDomains = collect($domainList)->pluck('domain')->filter()->values()->toArray();
            $testedDomainsStr = ! empty($testedDomains) ? implode(', ', $testedDomains) : 'Evaluated Domains';

            $program = strtolower((string) ($reportData['program'] ?? 'accountancy'));
            $frameworkContext = 'Philippine Certified Public Accountant Licensure Examination (PRC CPALE) administered by the Professional Regulation Commission (PRC) Board of Accountancy (BOA). All standards must adhere strictly to Philippine Accounting Standards (PAS), Philippine Financial Reporting Standards (PFRS), Philippine Standards on Auditing (PSA), Philippine Tax Code (NIRC as amended by CREATE / EOPT), and Regulatory Framework for Business Transactions (RFBT). NEVER cite US AICPA (FAR/AUD/REG/BEC) or US FASB ASC standards (e.g. ASC 606, ASC 842, ASC 230). Never refer to US GAAP or foreign state accountancy boards.';

            if (str_contains($program, 'psych')) {
                $frameworkContext = 'Philippine Board Licensure Examination for Psychometricians (PRC BLEPP) administered by the PRC Professional Regulatory Board of Psychology. Standards must adhere to RA 10029, Psychological Association of the Philippines (PAP) Code of Ethics, Psychological Assessment, Abnormal Psychology, Theories of Personality, and Industrial Psychology.';
            } elseif (str_contains($program, 'educ') || str_contains($program, 'teach')) {
                $frameworkContext = 'Philippine Licensure Examination for Teachers (PRC LET) administered by the PRC Board for Professional Teachers. Standards must adhere to Philippine Professional Standards for Teachers (PPST), Code of Ethics for Professional Teachers, General Education, Professional Education, and Specialization.';
            }

            $systemPrompt = "You are a professional licensure board examination adviser and academic diagnostician for the {$frameworkContext}\n".
                "Provide an actionable, realistic, high-yield study action plan based strictly on the student's mock board performance.\n".
                "CRITICAL REQUIREMENTS:\n".
                "- Only recommend priority domains that are within the student's tested subjects: [{$testedDomainsStr}] and where the student actually scored below 100%.\n".
                "- NEVER invent or hallucinate foreign standards, non-existent subjects, or topics not related to the curriculum.\n".
                "- Always reply with valid JSON containing keys: 'priority_domains' (array of strings), 'review_topics' (array of strings), 'study_steps' (array of 3 to 5 strings), 'summary_narrative' (string).";

            $userPrompt = "Student Performance Profile:\n".
                "- Readiness Score: {$reportData['summary']['readiness_percentage']}%\n".
                "- Passing Threshold: {$reportData['summary']['passing_threshold']}%\n".
                "- Likelihood Tier: {$reportData['summary']['tier_label']}\n".
                "- Gap to Pass: {$reportData['summary']['gap_percentage']}% ({$reportData['summary']['gap_items']} items)\n".
                "- Growth from Pre-Test: {$reportData['growth']['improvement_percentage']}%\n".
                '- Tested Domains: '.$testedDomainsStr."\n".
                '- Weakest Domains (< 100%): '.(empty($weakestDomains) ? 'None' : implode(', ', $weakestDomains))."\n".
                '- Missed Concept Samples: '.(empty($missedStems) ? 'None' : implode('; ', array_slice($missedStems, 0, 3)))."\n\n".
                'Produce the JSON action plan now:';

            $result = $this->ai->run($this->aiSettings->getModel(), [
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 650,
                'temperature' => 0.4,
            ]);

            $rawResponse = is_string($result['response'] ?? null)
                ? $result['response']
                : json_encode($result['response'] ?? '');

            $parsed = $this->parseActionPlanJson((string) $rawResponse);
            if (! empty($parsed['study_steps'])) {
                // Filter priority domains to only valid tested domains with score < 100%
                $validWeakDomainNames = collect($domainList)
                    ->filter(fn ($d) => ($d['post_score'] ?? 0) < 100)
                    ->pluck('domain')
                    ->all();

                $filteredPriorityDomains = array_values(array_filter(
                    (array) ($parsed['priority_domains'] ?? []),
                    fn ($dom) => in_array($dom, $validWeakDomainNames, true)
                ));

                // Reject study steps with US AICPA / ASC hallucinations
                $steps = (array) ($parsed['study_steps'] ?? []);
                $hasUsHallucination = false;
                foreach ($steps as $st) {
                    if (preg_match('/ASC\s*\d+|AICPA|US\s*GAAP/i', (string) $st)) {
                        $hasUsHallucination = true;
                        break;
                    }
                }

                return [
                    'priority_domains' => ! empty($filteredPriorityDomains) ? $filteredPriorityDomains : $fallback['priority_domains'],
                    'review_topics' => ! empty($parsed['review_topics']) ? $parsed['review_topics'] : $fallback['review_topics'],
                    'study_steps' => (! empty($steps) && ! $hasUsHallucination) ? $steps : $fallback['study_steps'],
                    'summary_narrative' => ! empty($parsed['summary_narrative']) ? $parsed['summary_narrative'] : $fallback['summary_narrative'],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Readiness AI Action Plan generation failed, using fallback plan', [
                'error' => $e->getMessage(),
            ]);
        }

        return $fallback;
    }

    /**
     * Parse structured JSON from raw LLM response.
     *
     * @return array<string, mixed>
     */
    protected function parseActionPlanJson(string $rawResponse): array
    {
        $raw = trim($rawResponse);
        if ($raw === '') {
            return [];
        }

        // Check for Markdown fenced json codeblock
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $raw, $matches)) {
            $raw = trim($matches[1]);
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return [
                'priority_domains' => (array) ($decoded['priority_domains'] ?? []),
                'review_topics' => (array) ($decoded['review_topics'] ?? []),
                'study_steps' => (array) ($decoded['study_steps'] ?? []),
                'summary_narrative' => (string) ($decoded['summary_narrative'] ?? ''),
            ];
        }

        return [];
    }

    /**
     * Deterministic rule-based fallback action plan.
     *
     * @param  array<string, mixed>  $reportData
     * @return array{priority_domains: array<string>, review_topics: array<string>, study_steps: array<string>, summary_narrative: string}
     */
    protected function buildFallbackActionPlan(array $reportData): array
    {
        $readiness = (float) ($reportData['summary']['readiness_percentage'] ?? 0);
        $threshold = (int) ($reportData['summary']['passing_threshold'] ?? 75);
        $domainList = (array) ($reportData['domains']['list'] ?? []);

        $weakDomains = collect($domainList)
            ->where('status', 'Weak')
            ->pluck('domain')
            ->values()
            ->all();

        if (empty($weakDomains)) {
            $weakDomains = collect($domainList)
                ->filter(fn ($d) => ($d['post_score'] ?? 0) < 100)
                ->sortBy('post_score')
                ->take(2)
                ->pluck('domain')
                ->values()
                ->all();
        }

        $allMastered = $readiness >= 100 || (empty($weakDomains) && ! empty($domainList));

        if ($allMastered) {
            return [
                'priority_domains' => [],
                'review_topics' => [],
                'study_steps' => [
                    '1. Maintain Mastery Through Spaced Retrieval: Schedule periodic active recall quizzes to retain theoretical frameworks and computational agility across all tested Philippine CPA syllabus topics.',
                    '2. Pacing and Time Management: Practice complete timed mock board simulations (3 hours per subject) to master pacing, time allocation per problem, and exam-day speed.',
                    '3. Stay Updated with Latest Regulatory Issuances: Review the latest BIR revenue regulations, PRC Board of Accountancy updates, and newly effective PFRS/PAS amendments.',
                    '4. Simulate Actual Licensure Exam Conditions: Rehearse under strict PRC CPALE examination conditions (non-programmable calculators, standard scratch paper, uninterrupted 3-hour blocks) to maximize mental stamina.',
                ],
                'summary_narrative' => 'Outstanding achievement! You have demonstrated 100% mastery across all evaluated domains. At this advanced level, your primary objective is sustained retention, exam-day time management, and staying aligned with the latest Philippine licensure examination standards.',
            ];
        }

        $missedTopics = [];
        foreach ($reportData['item_insights']['repeatedly_missed_items'] ?? [] as $item) {
            $missedTopics[] = "{$item['domain']}: {$item['stem']}";
        }
        foreach ($reportData['item_insights']['regressed_items'] ?? [] as $item) {
            $missedTopics[] = "{$item['domain']}: {$item['stem']}";
        }
        if (empty($missedTopics)) {
            foreach ($reportData['item_insights']['missed_post_items'] ?? [] as $item) {
                $missedTopics[] = "{$item['domain']}: {$item['stem']}";
            }
        }
        $missedTopics = array_values(array_unique($missedTopics));
        $missedTopics = array_slice($missedTopics, 0, 4);

        $studySteps = [];
        $domainListStr = ! empty($weakDomains) ? implode(' and ', $weakDomains) : 'core examination domains';
        $studySteps[] = "1. Allocate 60% of upcoming study time to comprehensive review in {$domainListStr}.";

        if (! empty($missedTopics)) {
            $studySteps[] = '2. Re-read standard theoretical frameworks and worked illustrations for repeatedly missed questions.';
        } else {
            $studySteps[] = '2. Conduct timed domain drill sessions to elevate speed and computational accuracy.';
        }

        if (($reportData['growth']['consistency_status'] ?? '') === 'Inconsistent') {
            $studySteps[] = '3. Build exam-day endurance: simulate full-length test conditions to reduce performance fluctuation.';
        } else {
            $studySteps[] = '3. Perform active spaced-repetition testing on definitions, special exceptions, and practical provisions.';
        }

        $studySteps[] = '4. Target a minimum +'.max(5, (int) round($reportData['summary']['gap_percentage'])).'% score gain on the next practice simulation.';

        $narrative = $readiness >= $threshold
            ? "Your performance confirms a solid mastery above the {$threshold}% benchmark. Prioritize fine-tuning low-scoring edge cases while sustaining domain retention."
            : "You are currently {$reportData['summary']['gap_percentage']}% shy of the {$threshold}% benchmark. Targeted remediation in {$domainListStr} provides the fastest pathway to secure your passing status.";

        return [
            'priority_domains' => $weakDomains,
            'review_topics' => $missedTopics,
            'study_steps' => $studySteps,
            'summary_narrative' => $narrative,
        ];
    }

    /**
     * Generate an Excel-compatible CSV export streamed directly to the student.
     */
    public function generateExcelExport(MockBoardReadinessReport $report, MockBoard $mockBoard, User $user): StreamedResponse
    {
        $fileName = sprintf(
            'MockBoard_Readiness_Report_%s_%s.csv',
            Str::slug($user->name ?: 'student'),
            date('Ymd_His')
        );

        return response()->streamDownload(function () use ($report, $mockBoard, $user) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header Section
            fputcsv($handle, ['REVISO - MOCK BOARD STUDENT READINESS REPORT']);
            fputcsv($handle, ['Report Generated At', $report->generated_at?->format('F d, Y h:i A') ?? now()->format('F d, Y h:i A')]);
            fputcsv($handle, ['Student Name', $user->name]);
            fputcsv($handle, ['Student Program', strtoupper($user->program ?? $mockBoard->program ?? 'N/A')]);
            fputcsv($handle, ['Mock Board Title', $mockBoard->title]);
            fputcsv($handle, ['Passing Benchmark', ($mockBoard->passing_percentage ?? 75).'%']);
            fputcsv($handle, []);

            // Summary Section
            fputcsv($handle, ['=== 1. READINESS KPI SUMMARY ===']);
            fputcsv($handle, ['Readiness Score (Best Post-Test)', $report->readiness_percentage.'%']);
            fputcsv($handle, ['Likelihood Tier', $report->tier_label]);
            fputcsv($handle, ['Gap to Pass Percentage', $report->gap_percentage.'%']);
            fputcsv($handle, ['Estimated Items Needed to Pass', $report->gap_items]);
            fputcsv($handle, ['Diagnostic Pre-Test Score', $report->pre_test_score.'%']);
            fputcsv($handle, ['Score Improvement (Gain)', ($report->improvement_percentage >= 0 ? '+' : '').$report->improvement_percentage.'%']);
            fputcsv($handle, ['Performance Consistency', $report->consistency_status]);
            fputcsv($handle, []);

            // Peer Benchmark Section
            if (! empty($report->peer_benchmark)) {
                $peer = $report->peer_benchmark;
                fputcsv($handle, ['=== 2. PEER & COHORT BENCHMARK ===']);
                fputcsv($handle, ['Batch Examinees Count', $peer['cohort_size'] ?? 0]);
                fputcsv($handle, ['Batch Average Score', ($peer['batch_average'] ?? 0).'%']);
                fputcsv($handle, ['Your Score vs Batch Average', (($peer['score_diff_from_batch'] ?? 0) >= 0 ? '+' : '').($peer['score_diff_from_batch'] ?? 0).'%']);
                fputcsv($handle, ['Percentile Rank', ($peer['percentile_rank'] ?? 0).'th Percentile']);
                fputcsv($handle, ['Cohort Standing', 'Higher than '.($peer['higher_than_percentage'] ?? 0).'% of examinees']);
                fputcsv($handle, []);
            }

            // Historical Comparison Section
            if (! empty($report->historical_comparison)) {
                $hist = $report->historical_comparison;
                fputcsv($handle, ['=== 3. HISTORICAL LICENSURE BENCHMARK ===']);
                fputcsv($handle, ['Exam Label', $hist['exam_label'] ?? 'PRC Licensure Exam']);
                fputcsv($handle, ['Exam Period / Year', $hist['exam_period_or_year'] ?? 'N/A']);
                fputcsv($handle, ['National Passing Rate', ($hist['national_passing_rate'] ?? 0).'%']);
                fputcsv($handle, ['Batch Mock Board Passing Rate', ($hist['batch_passing_rate'] ?? 0).'%']);
                fputcsv($handle, []);
            }

            // Domain Breakdown Table
            fputcsv($handle, ['=== 4. DOMAIN & SUBJECT MASTERY BREAKDOWN ===']);
            fputcsv($handle, ['Domain / Subject', 'Pre-Test Score', 'Post-Test Score', 'Growth', 'Correct Items', 'Total Items', 'Mastery Status']);

            $domains = $report->domain_breakdown['list'] ?? [];
            foreach ($domains as $d) {
                fputcsv($handle, [
                    $d['domain'],
                    $d['pre_score'] !== null ? $d['pre_score'].'%' : 'N/A',
                    $d['post_score'].'%',
                    $d['change'] !== null ? (($d['change'] >= 0 ? '+' : '').$d['change'].'%') : 'N/A',
                    $d['correct_items'] ?? 0,
                    $d['total_items'] ?? 0,
                    $d['status'],
                ]);
            }
            fputcsv($handle, []);

            // Item-Level Analysis
            if (! empty($report->item_insights)) {
                fputcsv($handle, ['=== 5. HIGH-YIELD ITEM & CONCEPTUAL GAPS ===']);
                fputcsv($handle, ['Category', 'Domain', 'Question Concept Stem', 'Explanation']);

                foreach ($report->item_insights['repeatedly_missed_items'] ?? [] as $item) {
                    fputcsv($handle, ['Repeatedly Missed', $item['domain'], $item['stem'], $item['explanation'] ?? '']);
                }
                foreach ($report->item_insights['regressed_items'] ?? [] as $item) {
                    fputcsv($handle, ['Regressed Item', $item['domain'], $item['stem'], $item['explanation'] ?? '']);
                }
                fputcsv($handle, []);
            }

            // AI Action Plan
            if (! empty($report->ai_action_plan)) {
                $plan = $report->ai_action_plan;
                fputcsv($handle, ['=== 6. PERSONALIZED ACTION PLAN ===']);
                fputcsv($handle, ['Readiness Summary Narrative', $plan['summary_narrative'] ?? '']);
                fputcsv($handle, ['Priority Focus Domains', implode(', ', (array) ($plan['priority_domains'] ?? []))]);
                fputcsv($handle, ['Key Concepts for Review', implode(' | ', (array) ($plan['review_topics'] ?? []))]);
                fputcsv($handle, ['Step-by-Step Study Plan']);
                foreach ((array) ($plan['study_steps'] ?? []) as $step) {
                    fputcsv($handle, ['-', $step]);
                }
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
