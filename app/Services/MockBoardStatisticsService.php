<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\MockBoard;
use App\Models\MockBoardAttempt;
use App\Models\MockBoardStatistic;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Support\Collection;

class MockBoardStatisticsService
{
    /**
     * Compute class-level statistics for a mock board.
     * Calculates means, std deviations for Pre-Test and Pre-Boards.
     */
    public function computeClassStatistics(MockBoard $mockBoard): MockBoardStatistic
    {
        // Get all attempts by phase
        $preTestAttempts = MockBoardAttempt::where('mock_board_id', $mockBoard->id)
            ->where('phase_type', 'pre_test')
            ->whereNotNull('percentage')
            ->get();

        $preBoardsAttempts = MockBoardAttempt::where('mock_board_id', $mockBoard->id)
            ->where('phase_type', 'pre_boards')
            ->whereNotNull('percentage')
            ->get();

        // Compute statistics
        $preTestStats = $this->calculateStats($preTestAttempts);
        $preBoardsStats = $this->calculateStats($preBoardsAttempts);

        // Compute ANOVA
        $anovaResults = $this->computeOneWayANOVA(
            $preTestAttempts->pluck('percentage')->toArray(),
            $preBoardsAttempts->pluck('percentage')->toArray()
        );

        // Calculate improvement percentage
        $improvementPercentage = null;
        if ($preTestStats['mean'] !== null && $preBoardsStats['mean'] !== null) {
            $improvementPercentage = round(
                (($preBoardsStats['mean'] - $preTestStats['mean']) / max($preTestStats['mean'], 1)) * 100,
                2
            );
        }

        // Save or update statistics
        $statistics = MockBoardStatistic::updateOrCreate(
            ['mock_board_id' => $mockBoard->id],
            [
                'class_id' => $mockBoard->class_id,
                'pre_test_count' => $preTestStats['count'],
                'pre_test_mean' => $preTestStats['mean'],
                'pre_test_std_dev' => $preTestStats['std_dev'],
                'pre_boards_count' => $preBoardsStats['count'],
                'pre_boards_mean' => $preBoardsStats['mean'],
                'pre_boards_std_dev' => $preBoardsStats['std_dev'],
                'anova_f_statistic' => $anovaResults['f_statistic'],
                'anova_p_value' => $anovaResults['p_value'],
                'anova_significant' => $anovaResults['significant'],
                'improvement_percentage' => $improvementPercentage,
                'computed_at' => now(),
            ]
        );

        return $statistics;
    }

    /**
     * Calculate basic statistics (count, mean, std dev) for a collection of attempts.
     */
    private function calculateStats(Collection $attempts): array
    {
        $count = $attempts->count();

        if ($count === 0) {
            return ['count' => 0, 'mean' => null, 'std_dev' => null];
        }

        $values = $attempts->pluck('percentage')->toArray();
        $mean = array_sum($values) / $count;

        // Sample standard deviation
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $stdDev = $count > 1 ? sqrt($variance / ($count - 1)) : 0;

        return [
            'count' => $count,
            'mean' => round($mean, 2),
            'std_dev' => round($stdDev, 2),
        ];
    }

    /**
     * Compute one-way ANOVA between two groups.
     * Returns F-statistic, p-value, and significance flag.
     */
    public function computeOneWayANOVA(array $group1, array $group2): array
    {
        $n1 = count($group1);
        $n2 = count($group2);

        // Kailangan ng sapat na total samples (n1 + n2 > 2) para may
        // positive degrees of freedom within groups (dfWithin > 0),
        // kundi mag-di-divide-by-zero sa msw calculation sa baba.
        if ($n1 === 0 || $n2 === 0 || ($n1 + $n2 - 2) <= 0) {
            return [
                'f_statistic' => null,
                'p_value' => null,
                'significant' => null,
            ];
        }

        // Group means
        $mean1 = array_sum($group1) / $n1;
        $mean2 = array_sum($group2) / $n2;

        // Grand mean
        $grandMean = (array_sum($group1) + array_sum($group2)) / ($n1 + $n2);

        // Sum of squares between groups (SSB)
        $ssb = $n1 * pow($mean1 - $grandMean, 2) + $n2 * pow($mean2 - $grandMean, 2);

        // Sum of squares within groups (SSW)
        $ssw1 = 0;
        foreach ($group1 as $value) {
            $ssw1 += pow($value - $mean1, 2);
        }
        $ssw2 = 0;
        foreach ($group2 as $value) {
            $ssw2 += pow($value - $mean2, 2);
        }
        $ssw = $ssw1 + $ssw2;

        // Degrees of freedom
        $dfBetween = 1; // k - 1 = 2 - 1 = 1
        $dfWithin = $n1 + $n2 - 2; // N - k

        // Mean squares
        $msb = $ssb / $dfBetween;
        $msw = $ssw / $dfWithin;

        // F-statistic
        $fStatistic = $msw > 0 ? $msb / $msw : 0;

        // Approximate p-value using F-distribution
        $pValue = $this->approximatePValue($fStatistic, $dfBetween, $dfWithin);

        return [
            'f_statistic' => round($fStatistic, 4),
            'p_value' => round($pValue, 6),
            'significant' => $pValue < 0.05,
        ];
    }

    /**
     * Approximate p-value for F-distribution using simplified method.
     * For production, consider using a proper statistical library.
     */
    private function approximatePValue(float $f, int $df1, int $df2): float
    {
        // Simplified approximation using F-distribution properties
        // For educational context, this approximation is sufficient
        if ($f <= 0) {
            return 1.0;
        }

        // Use F-distribution CDF approximation
        $x = ($df1 * $f) / ($df1 * $f + $df2);
        $a = $df1 / 2;
        $b = $df2 / 2;

        // Regularized incomplete beta function approximation
        return $this->betaIncomplete($x, $a, $b);
    }

    /**
     * Simplified incomplete beta function for p-value calculation.
     */
    private function betaIncomplete(float $x, float $a, float $b): float
    {
        // Simplified approximation - sufficient for educational context
        // For more precision, use a statistical library like 'php-statistics'

        if ($x <= 0) {
            return 1.0;
        }
        if ($x >= 1) {
            return 0.0;
        }

        // Use continued fraction approximation for I_x(a,b)
        $maxIterations = 200;
        $epsilon = 3e-7;
        $am = 1.0;
        $bm = 1.0;
        $az = 1.0;
        $bz = 1.0 - ($a + $b) * $x / ($a + 1.0);
        $qab = $a + $b;
        $qap = $a + 1.0;
        $qam = $a - 1.0;
        $ap = $az;
        $bp = $bz;

        for ($m = 1; $m <= $maxIterations; $m++) {
            $m2 = 2 * $m;
            $d = $m * ($b - $m) * $x / (($qam + $m2) * ($a + $m2));
            $ap = $az + $d * $am;
            $bp = $bz + $d * $bm;
            $d = -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($qap + $m2));
            $app = $ap + $d * $az;
            $bpp = $bp + $d * $bz;
            $aOld = $az;
            $am = $ap / $bpp;
            $bm = $bp / $bpp;
            $az = $app / $bpp;
            $bz = 1.0;

            if (abs($az - $aOld) < $epsilon * abs($az)) {
                break;
            }
        }

        return 1.0 - $az * pow($x, $a) * pow(1.0 - $x, $b) / $a;
    }

    /**
     * Compute paired t-test for pre/post scores of same students.
     * More precise than ANOVA when same students take both tests.
     */
    public function computePairedTTest(MockBoard $mockBoard): array
    {
        // Get students who took both phases
        $attempts = MockBoardAttempt::where('mock_board_id', $mockBoard->id)
            ->whereNotNull('percentage')
            ->get()
            ->groupBy('user_id')
            ->filter(function ($group) {
                return $group->count() === 2; // Has both pre_test and pre_boards
            });

        if ($attempts->count() < 2) {
            return [
                'mean_difference' => null,
                't_statistic' => null,
                'degrees_of_freedom' => null,
                'p_value' => null,
                'significant' => null,
            ];
        }

        $differences = [];
        foreach ($attempts as $userAttempts) {
            $preTest = $userAttempts->firstWhere('phase_type', 'pre_test')?->percentage ?? 0;
            $preBoards = $userAttempts->firstWhere('phase_type', 'pre_boards')?->percentage ?? 0;
            $differences[] = $preBoards - $preTest;
        }

        $n = count($differences);
        $meanDiff = array_sum($differences) / $n;

        // Standard deviation of differences
        $sumSquaredDiff = 0;
        foreach ($differences as $diff) {
            $sumSquaredDiff += pow($diff - $meanDiff, 2);
        }
        $stdDevDiff = $n > 1 ? sqrt($sumSquaredDiff / ($n - 1)) : 0;

        // t-statistic
        $tStatistic = $stdDevDiff > 0 ? $meanDiff / ($stdDevDiff / sqrt($n)) : 0;
        $df = $n - 1;

        // Approximate p-value using t-distribution
        $pValue = $this->approximateTDistPValue(abs($tStatistic), $df);

        return [
            'mean_difference' => round($meanDiff, 2),
            't_statistic' => round($tStatistic, 4),
            'degrees_of_freedom' => $df,
            'p_value' => round($pValue, 6),
            'significant' => $pValue < 0.05,
        ];
    }

    /**
     * Approximate p-value for t-distribution.
     */
    private function approximateTDistPValue(float $t, int $df): float
    {
        // Simplified approximation using relationship with F-distribution
        // t^2 ~ F(1, df)
        $f = $t * $t;
        $fResult = $this->computeOneWayANOVA([0], array_fill(0, $df + 1, 0));

        // Direct approximation for t-distribution
        $x = $df / ($df + $t * $t);
        $beta = $this->betaIncomplete($x, $df / 2, 0.5);

        return $beta;
    }

    /**
     * Get item analysis for mock board questions.
     * Calculates difficulty, discrimination, distractor option distribution, and top/bottom statistics.
     */
    public function getItemAnalysis(MockBoard $mockBoard, ?string $phaseType = null, ?int $phaseId = null, ?int $classId = null): array
    {
        $query = MockBoardAttempt::where('mock_board_id', $mockBoard->id)
            ->whereHas('quizAttempt.answers') // Ensures attempt actually has answer records
            ->with(['quizAttempt.answers', 'user.classes']);

        if ($phaseId) {
            $query->where('mock_board_phase_id', $phaseId);
        } elseif ($phaseType) {
            $query->where('phase_type', $phaseType);
        }

        if ($classId) {
            $query->whereHas('user.classes', function ($q) use ($classId) {
                $q->where('classes.id', $classId);
            });
        }

        $attempts = $query->get();

        if ($attempts->isEmpty()) {
            return [];
        }

        $questionStats = [];
        foreach ($attempts as $attempt) {
            if (! $attempt->quizAttempt || ! $attempt->quizAttempt->answers) {
                continue;
            }

            foreach ($attempt->quizAttempt->answers as $answer) {
                $questionId = $answer->question_id;
                if (! isset($questionStats[$questionId])) {
                    $questionStats[$questionId] = [
                        'correct' => 0,
                        'total' => 0,
                        'option_counts' => [
                            'A' => 0,
                            'B' => 0,
                            'C' => 0,
                            'D' => 0,
                        ],
                        'scores' => [],
                    ];
                }

                $isCorrect = $answer->is_correct ?? false;
                if ($isCorrect) {
                    $questionStats[$questionId]['correct']++;
                }
                $questionStats[$questionId]['total']++;

                $chosenOpt = strtoupper(trim($answer->selected_option ?? ''));
                if (isset($questionStats[$questionId]['option_counts'][$chosenOpt])) {
                    $questionStats[$questionId]['option_counts'][$chosenOpt]++;
                } elseif ($chosenOpt !== '') {
                    $questionStats[$questionId]['option_counts'][$chosenOpt] = 1;
                }

                $questionStats[$questionId]['scores'][] = [
                    'student_id' => $attempt->user_id,
                    'score' => $attempt->percentage,
                    'correct' => $isCorrect,
                ];
            }
        }

        $analysis = [];
        foreach ($questionStats as $questionId => $stats) {
            $difficulty = $stats['total'] > 0 ? $stats['correct'] / $stats['total'] : 0;

            usort($stats['scores'], function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            $top27 = (int) ceil(count($stats['scores']) * 0.27);
            $bottom27 = $top27;

            $topCorrect = 0;
            $bottomCorrect = 0;

            for ($i = 0; $i < $top27 && $i < count($stats['scores']); $i++) {
                if ($stats['scores'][$i]['correct']) {
                    $topCorrect++;
                }
            }

            for ($i = count($stats['scores']) - $bottom27; $i < count($stats['scores']); $i++) {
                if ($i >= 0 && $stats['scores'][$i]['correct']) {
                    $bottomCorrect++;
                }
            }

            $topRate = $top27 > 0 ? $topCorrect / $top27 : 0;
            $bottomRate = $bottom27 > 0 ? $bottomCorrect / $bottom27 : 0;
            $discrimination = $topRate - $bottomRate;

            $reliableSample = $stats['total'] >= 10;

            $distractorPercentages = [];
            foreach ($stats['option_counts'] as $opt => $cnt) {
                $distractorPercentages[$opt] = $stats['total'] > 0 ? round(($cnt / $stats['total']) * 100, 1) : 0;
            }

            $analysis[$questionId] = [
                'question_id' => $questionId,
                'difficulty' => round($difficulty, 2),
                'discrimination' => $reliableSample ? round($discrimination, 2) : null,
                'correct_count' => $stats['correct'],
                'total_count' => $stats['total'],
                'option_counts' => $stats['option_counts'],
                'option_percentages' => $distractorPercentages,
                'interpretation' => $this->interpretItem($difficulty, $discrimination),
            ];
        }

        $questionIds = array_keys($analysis);
        $questions = QuizQuestion::whereIn('id', $questionIds)
            ->get()
            ->keyBy('id');

        foreach ($analysis as $questionId => &$data) {
            $q = $questions->get($questionId);
            $data['question_text'] = $q->question_text ?? 'Question not found';
            $data['options'] = is_array($q?->options) ? $q->options : (json_decode($q?->options ?? '[]', true) ?? []);
            $data['correct_option'] = $q?->correct_option;
            $data['order'] = $q->order ?? null;
        }
        unset($data);

        return collect($analysis)->sortBy('order')->values()->all();
    }

    /**
     * Get individual student question-by-question item analysis for a mock board.
     */
    public function getStudentMockBoardItemAnalysis(MockBoard $mockBoard, User $student, ?int $phaseId = null): array
    {
        $phases = $mockBoard->phases()
            ->with(['module.quizQuestions'])
            ->orderByRaw("CASE WHEN phase_type = 'pre_test' THEN 1 ELSE 2 END ASC, sequence_number ASC")
            ->get();

        if ($phases->isEmpty()) {
            return [
                'phases' => [],
                'active_phase' => null,
                'summary' => [
                    'total_questions' => 0,
                    'correct_count' => 0,
                    'incorrect_count' => 0,
                    'score_percentage' => 0,
                    'passed' => false,
                    'attempted_at' => 'No phases configured',
                ],
                'questions' => [],
            ];
        }

        $activePhase = $phaseId ? $phases->firstWhere('id', $phaseId) : $phases->first();
        if (! $activePhase) {
            $activePhase = $phases->first();
        }

        $phasesList = $phases->map(function ($p) use ($activePhase, $student) {
            $att = MockBoardAttempt::where('mock_board_phase_id', $p->id)
                ->where('user_id', $student->id)
                ->first();

            return [
                'id' => $p->id,
                'phase_type' => $p->phase_type,
                'phase_label' => $p->phase_label,
                'is_active' => $p->id === $activePhase->id,
                'has_attempt' => $att !== null,
                'percentage' => $att?->percentage,
                'passed' => $att?->passed,
            ];
        })->values()->all();

        $attempt = MockBoardAttempt::where('mock_board_phase_id', $activePhase->id)
            ->where('user_id', $student->id)
            ->with('quizAttempt.answers')
            ->first();

        if (! $attempt) {
            $attempt = MockBoardAttempt::where('mock_board_id', $mockBoard->id)
                ->where('phase_type', $activePhase->phase_type)
                ->where('user_id', $student->id)
                ->with('quizAttempt.answers')
                ->first();
        }

        $answersMap = collect();
        if ($attempt && $attempt->quizAttempt && $attempt->quizAttempt->answers) {
            $answersMap = $attempt->quizAttempt->answers->keyBy('question_id');
        }

        $questionsList = [];
        $correctCount = 0;

        $moduleQuestions = $activePhase->module?->quizQuestions ?? collect();
        if ($moduleQuestions->isEmpty() && $activePhase->question_ids) {
            $moduleQuestions = QuizQuestion::whereIn('id', $activePhase->question_ids)->get();
        }

        $moduleQuestions = $moduleQuestions->sortBy('order')->values();
        $totalQuestions = $moduleQuestions->count();

        foreach ($moduleQuestions as $idx => $q) {
            $ans = $answersMap->get($q->id);
            $selectedOpt = $ans?->selected_option;
            $isCorrect = $ans?->is_correct ?? false;
            if ($isCorrect) {
                $correctCount++;
            }

            $options = is_array($q->options) ? $q->options : (json_decode($q->options, true) ?? []);

            $questionsList[] = [
                'question_id' => $q->id,
                'order' => $q->order ?? ($idx + 1),
                'question_text' => $q->question_text,
                'options' => $options,
                'correct_option' => $q->correct_option,
                'correct_option_text' => $options[$q->correct_option] ?? $q->correct_option,
                'selected_option' => $selectedOpt,
                'selected_option_text' => $selectedOpt ? ($options[$selectedOpt] ?? $selectedOpt) : 'Not Answered',
                'is_correct' => $isCorrect,
                'is_answered' => $selectedOpt !== null,
                'points' => $q->points ?? 1,
                'points_earned' => $isCorrect ? ($q->points ?? 1) : 0,
                'difficulty' => $q->difficulty ?? 'Normal',
                'explanation' => $q->explanation,
            ];
        }

        $percentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 1) : ($attempt?->percentage ?? 0);
        $passingThreshold = $mockBoard->passing_percentage ?? 75;

        return [
            'phases' => $phasesList,
            'active_phase' => [
                'id' => $activePhase->id,
                'phase_label' => $activePhase->phase_label,
                'phase_type' => $activePhase->phase_type,
            ],
            'summary' => [
                'total_questions' => $totalQuestions,
                'correct_count' => $correctCount,
                'incorrect_count' => $totalQuestions - $correctCount,
                'score_percentage' => $percentage,
                'passed' => $percentage >= $passingThreshold,
                'attempted_at' => $attempt?->created_at?->format('M d, Y h:i A') ?? 'Not Taken Yet',
            ],
            'questions' => $questionsList,
        ];
    }

    /**
     * Interpret item difficulty and discrimination.
     */
    private function interpretItem(float $difficulty, float $discrimination): string
    {
        if ($difficulty < 0.2) {
            return 'Very Difficult';
        } elseif ($difficulty < 0.4) {
            return 'Difficult';
        } elseif ($difficulty < 0.6) {
            return 'Moderate';
        } elseif ($difficulty < 0.8) {
            return 'Easy';
        } else {
            return 'Very Easy';
        }
    }

    /**
     * Compute batch-level passing rates across multiple classes/programs.
     */

    /**
     * Generates the multi-level averages: Individual -> Class -> Batch
     */
    public function computeHierarchicalBatchStats(string $program, int $mockBoardId): array
    {
        $mockBoard = MockBoard::findOrFail($mockBoardId);
        $passingScore = $mockBoard->passing_percentage ?? 75;

        $programVariants = $this->resolveProgramVariants($program);

        // Ang forecast ay dapat batay sa Pre-Boards phase lang (mas malapit
        // ito sa totoong board exam kaysa sa Pre-Test), kaya i-filter dito.
        $allAttempts = MockBoardAttempt::where('mock_board_id', $mockBoardId)
            ->where('phase_type', 'pre_boards')
            ->whereHas('user', function ($q) use ($programVariants) {
                $q->whereIn('program', $programVariants);
            })
            ->with('user.classes') // Ensure we can see which class they belong to
            ->get();

        // 1. Group by Class ID
        $groupedByClass = $allAttempts->groupBy(function ($attempt) {
            return $attempt->user->classes->first()->id ?? 'unassigned';
        });

        $classStats = [];
        foreach ($groupedByClass as $classId => $attempts) {
            $className = $attempts->first()->user->classes->first()->name ?? 'Unassigned Students';

            $classStats[] = [
                'class_id' => $classId,
                'class_name' => $className,
                'average_score' => round($attempts->avg('percentage'), 2),
                'passing_rate' => $attempts->count() > 0
                    ? round(($attempts->where('passed', true)->count() / $attempts->count()) * 100, 2)
                    : 0,
                'student_count' => $attempts->unique('user_id')->count(),
            ];
        }

        // 2. Calculate Overall Batch Stats
        $batchTotal = $allAttempts->count();
        $batchPassingRate = $batchTotal > 0
            ? round(($allAttempts->where('passed', true)->count() / $batchTotal) * 100, 2)
            : 0;

        return [
            'batch_average' => $allAttempts->isNotEmpty() ? round($allAttempts->avg('percentage'), 2) : 0,
            'batch_passing_rate' => $batchPassingRate,
            'total_batch_students' => $allAttempts->unique('user_id')->count(),
            'classes' => $classStats,
        ];
    }

    public function computeHierarchicalStats(MockBoard $mockBoard, string $program)
    {
        $programVariants = $this->resolveProgramVariants($program);
        $classes = ClassModel::where(function ($q) use ($program, $programVariants) {
            $q->whereIn('program', $programVariants)
                ->orWhereRaw('LOWER(program) = ?', [strtolower($program)]);
        })->get();

        $batchTotalStudents = 0;
        $batchTotalPassed = 0;
        $classData = [];

        foreach ($classes as $class) {
            $studentIds = $class->students()->pluck('users.id');

            $attempts = $mockBoard->attempts()
                ->whereIn('user_id', $studentIds)
                ->where('phase_type', 'pre_boards')
                ->get();

            $studentCount = $attempts->count();
            $passedCount = $attempts->where('passed', true)->count();
            $averageScore = $studentCount > 0 ? round($attempts->avg('percentage'), 2) : 0;
            $passingRate = $studentCount > 0 ? round(($passedCount / $studentCount) * 100, 2) : 0;

            $classData[] = [
                'class_name' => $class->name,
                'student_count' => $studentCount,
                'passing_rate' => $passingRate,
                'average_score' => $averageScore,
            ];

            $batchTotalStudents += $studentCount;
            $batchTotalPassed += $passedCount;
        }

        return [
            'classes' => $classData,
            'total_batch_students' => $batchTotalStudents,
            'batch_passing_rate' => $batchTotalStudents > 0
                ? round(($batchTotalPassed / $batchTotalStudents) * 100, 2)
                : 0,
        ];
    }

    public function getDetailedStudentResults(MockBoard $mockBoard, string $program)
    {
        $programVariants = $this->resolveProgramVariants($program);

        // 1. Get all students belonging to this program
        $programUsers = User::where(function ($q) use ($program, $programVariants) {
            $q->whereIn('program', $programVariants)
                ->orWhereRaw('LOWER(program) = ?', [strtolower($program)]);
        })
            ->where('role', 'student')
            ->with('classes')
            ->get();

        // 2. Get all attempts for this specific mock board
        $allAttempts = $mockBoard->attempts()
            ->with('user.classes')
            ->get();

        $attemptUsers = $allAttempts->pluck('user')->filter()->unique('id');

        // Merge program students and actual attempt users
        $users = $programUsers->concat($attemptUsers)->unique('id');

        $studentResults = [];

        // Set this to false if you want to HIDE students who haven't started
        $includeIncomplete = false;

        foreach ($users as $user) {
            $userAttempts = $allAttempts->where('user_id', $user->id);
            $preTest = $userAttempts->where('phase_type', 'pre_test')->first();
            $preBoards = $userAttempts->where('phase_type', 'pre_boards')->sortByDesc('percentage')->first()
                ?? $userAttempts->where('phase_type', 'pre_boards')->first();

            // Skip students with no data if we don't want incomplete records
            if (! $includeIncomplete && ! $preTest && ! $preBoards) {
                continue;
            }

            $preTestScore = $preTest ? $preTest->percentage : null;
            $preBoardsScore = $preBoards ? $preBoards->percentage : null;

            // Calculate improvement only if both phases are completed
            $improvement = null;
            if ($preTestScore !== null && $preBoardsScore !== null) {
                $improvement = $preBoardsScore - $preTestScore;
            }

            $currentScore = $preBoardsScore !== null ? $preBoardsScore : $preTestScore;
            $likelihood = 'not_started';
            if ($currentScore !== null) {
                if ($currentScore >= 75) {
                    $likelihood = 'high';
                } elseif ($currentScore >= 65) {
                    $likelihood = 'moderate';
                } else {
                    $likelihood = 'low';
                }
            }

            $completedAt = $preBoards?->created_at?->format('M d, Y')
                ?? $preTest?->created_at?->format('M d, Y')
                ?? 'Pending';

            $studentResults[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'program' => ucfirst($user->program ?? $program),
                'class_name' => $user->classes->first()?->name ?? 'N/A',
                'pre_test_score' => $preTestScore,
                'pre_test_passed' => $preTest ? (bool) $preTest->passed : false,
                'pre_boards_score' => $preBoardsScore,
                'pre_boards_passed' => $preBoards ? (bool) $preBoards->passed : false,
                'improvement' => $improvement,
                'board_likelihood' => $likelihood,
                'completed_at' => $completedAt,
            ];
        }

        // Sort by name for a cleaner table
        return collect($studentResults)->sortBy('name')->values()->all();
    }

    public function calculateBatchANOVA(MockBoard $mockBoard, string $program)
    {
        $programVariants = $this->resolveProgramVariants($program);

        $attempts = $mockBoard->attempts()
            ->whereHas('user', function ($q) use ($program, $programVariants) {
                $q->whereIn('program', $programVariants)
                    ->orWhereRaw('LOWER(program) = ?', [strtolower($program)]);
            })
            ->get()
            ->groupBy('user_id');

        $preTestScores = [];
        $preBoardsScores = [];

        foreach ($attempts as $userAttempts) {
            $preTest = $userAttempts->firstWhere('phase_type', 'pre_test');
            $preBoards = $userAttempts->firstWhere('phase_type', 'pre_boards');

            if ($preTest && $preTest->percentage !== null) {
                $preTestScores[] = $preTest->percentage;
            }
            if ($preBoards && $preBoards->percentage !== null) {
                $preBoardsScores[] = $preBoards->percentage;
            }
        }

        // Call your existing internal ANOVA logic
        return $this->computeOneWayANOVA($preTestScores, $preBoardsScores);
    }

    /**
     * Updates or creates the summary record for a specific mock board.
     * This should be called every time a student submits an exam.
     */
    public function updateMockBoardSummary(MockBoard $mockBoard)
    {
        // 1. Get all students in the program (e.g., Education)
        $totalStudents = User::where('program', $mockBoard->program)
            ->where('role', 'student')
            ->count();

        // 2. Aggregate counts and means for this specific board
        $preTestAttempts = $mockBoard->attempts()->where('phase_type', 'pre_test')->get();
        $preBoardAttempts = $mockBoard->attempts()->where('phase_type', 'pre_boards')->get();

        $preTestMean = $preTestAttempts->avg('percentage') ?? 0;
        $preBoardMean = $preBoardAttempts->avg('percentage') ?? 0;

        // 3. Update the statistics table
        return MockBoardStatistic::updateOrCreate(
            ['mock_board_id' => $mockBoard->id],
            [
                'total_students' => $totalStudents,
                'pre_test_count' => $preTestAttempts->count(),
                'pre_boards_count' => $preBoardAttempts->count(),
                'pre_test_mean' => round($preTestMean, 2),
                'pre_boards_mean' => round($preBoardMean, 2),
                'improvement_percentage' => round($preBoardMean - $preTestMean, 2),
                // Re-run ANOVA if both phases have data
                'anova_significant' => $this->calculateBasicSignificance($preTestAttempts, $preBoardAttempts),
                'last_updated' => now(),
            ]
        );
    }

    /**
     * Simple helper to check if progress is statistically notable
     */
    private function calculateBasicSignificance($preTest, $preBoard)
    {
        if ($preTest->count() < 2 || $preBoard->count() < 2) {
            return false;
        }

        // For now, we'll mark it true if the mean improved by more than 5%
        // You can replace this with your full ANOVA logic later
        return ($preBoard->avg('percentage') - $preTest->avg('percentage')) > 5;
    }
    /**
     * Renamed to match the Controller's call
     */

    /**
     * Phase A forecast: a labeled projection (NOT a calibrated prediction)
     * based on the batch's own Pre-Boards passing rate. Wraps the existing
     * hierarchical batch stats and adds forecast-specific metadata.
     */
    public function computeForecastedPassRate(MockBoard $mockBoard, string $program): array
    {
        $batchStats = $this->computeHierarchicalBatchStats($program, $mockBoard->id);

        return [
            'projected_batch_pass_rate' => $batchStats['batch_passing_rate'] ?? 0,
            'sample_size' => $batchStats['total_batch_students'] ?? 0,
            'confidence_note' => 'Based on internal Pre-Board mock exam results, not an official prediction.',
            'batch_average' => $batchStats['batch_average'] ?? 0,
            'classes' => $batchStats['classes'] ?? [],
        ];
    }

    /**
     * Ang mga program value sa `users.program` ay hindi consistent
     * (accountancy, psych, education — halong short at long form).
     * Ito ang tumutugma sa lahat ng alternatibong spelling para hindi
     * mag-0 ang results kapag naipasa ang "psychology" o "educ" mula
     * sa ibang caller (hal. dashboard() na gumagamit ng long form).
     */
    private function resolveProgramVariants(string $program): array
    {
        $map = [
            'psych' => ['psych', 'psychology'],
            'psychology' => ['psych', 'psychology'],
            'educ' => ['educ', 'education'],
            'education' => ['educ', 'education'],
            'accountancy' => ['accountancy'],
        ];

        return $map[strtolower($program)] ?? [$program];
    }

    public function getHierarchicalStats(MockBoard $mockBoard)
    {
        // Use the board's program
        $program = $mockBoard->program;
        $classes = ClassModel::where('program', $program)->get();

        $classData = [];

        foreach ($classes as $class) {
            // Gamitin ang tunay na many-to-many relationship (class_user pivot)
            // imbes na hanapin ang isang class_id column sa users table na
            // hindi naman ginagamit dito.
            $studentIds = $class->students()->pluck('users.id');

            $attempts = $mockBoard->attempts()
                ->whereIn('user_id', $studentIds)
                ->get();

            // Count unique students who have started AT LEAST one phase
            $uniqueStudentIds = $attempts->pluck('user_id')->unique();
            $studentCount = $uniqueStudentIds->count();

            // A student "passed" if their LATEST phase attempt is passed
            $passedCount = 0;
            foreach ($uniqueStudentIds as $userId) {
                $latestAttempt = $attempts->where('user_id', $userId)
                    ->sortByDesc(fn ($a) => $a->phase_type === 'pre_boards' ? 1 : 0)
                    ->first();

                if ($latestAttempt && $latestAttempt->passed) {
                    $passedCount++;
                }
            }

            $classData[] = [
                'class_name' => $class->name,
                'student_count' => $studentCount,
                'passing_rate' => $studentCount > 0 ? round(($passedCount / $studentCount) * 100, 2) : 0,
                'average_score' => $studentCount > 0 ? round($attempts->avg('percentage'), 2) : 0,
            ];
        }

        return ['classes' => $classData];
    }

    /**
     * Overall post-test ("Pre-Boards" type) passing rate across ALL post-test
     * phases of a mock board, combined per student.
     *
     * Per the confirmed product rule ("best score if individually"): a
     * student's pass/fail is based on the BEST percentage they achieved across
     * whatever post-test phases they attempted — not an average of all their
     * attempts, and not requiring them to pass every phase. The program/board
     * level "overall passing rate" is then the percentage of students (who
     * attempted at least one post-test phase) whose best score met the
     * mock board's passing_percentage.
     *
     * @return array{
     *     overall_passing_rate: float,
     *     students_attempted: int,
     *     students_passed: int,
     *     phases_total: int,
     *     per_student: array<int, array{user_id: int, best_percentage: int, passed: bool, phases_attempted: int}>
     * }
     */
    public function computeOverallPostTestPassingRate(MockBoard $mockBoard): array
    {
        $postTestPhaseIds = $mockBoard->phases()->postTests()->pluck('id');

        $attempts = MockBoardAttempt::whereIn('mock_board_phase_id', $postTestPhaseIds)
            ->whereNotNull('percentage')
            ->get()
            ->groupBy('user_id');

        $passingScore = $mockBoard->passing_percentage ?? 75;

        $perStudent = [];
        $studentsPassed = 0;

        foreach ($attempts as $userId => $userAttempts) {
            // Best score across every post-test phase this student attempted —
            // never an average, per the confirmed product rule.
            $best = $userAttempts->max('percentage');
            $passed = $best >= $passingScore;

            if ($passed) {
                $studentsPassed++;
            }

            $perStudent[] = [
                'user_id' => $userId,
                'best_percentage' => $best,
                'passed' => $passed,
                'phases_attempted' => $userAttempts->count(),
            ];
        }

        $studentsAttempted = count($perStudent);

        return [
            'overall_passing_rate' => $studentsAttempted > 0
                ? round(($studentsPassed / $studentsAttempted) * 100, 2)
                : 0.0,
            'students_attempted' => $studentsAttempted,
            'students_passed' => $studentsPassed,
            'phases_total' => $postTestPhaseIds->count(),
            'per_student' => $perStudent,
        ];
    }

    /**
     * Compute institutional and program-level post-test passing rate analytics for Admin.
     *
     * @return array{
     *     overall: array{
     *         passing_rate: float,
     *         students_attempted: int,
     *         students_passed: int,
     *         boards_count: int,
     *         average_score: float
     *     },
     *     by_program: array<string, array{
     *         program_key: string,
     *         label: string,
     *         passing_rate: float,
     *         students_attempted: int,
     *         students_passed: int,
     *         boards_count: int,
     *         average_score: float
     *     }>
     * }
     */
    public function getAdminPostTestAnalytics(): array
    {
        $programDefinitions = [
            'accountancy' => [
                'label' => 'Accountancy (BSA)',
                'variants' => ['accountancy'],
            ],
            'education' => [
                'label' => 'Education (BSED/BEED)',
                'variants' => ['education', 'educ'],
            ],
            'psychology' => [
                'label' => 'Psychology (BS Psych)',
                'variants' => ['psychology', 'psych'],
            ],
        ];

        $byProgram = [];
        $totalTakersAll = 0;
        $totalPassedAll = 0;
        $totalScoreSumAll = 0;
        $totalBoardsAll = 0;

        foreach ($programDefinitions as $key => $def) {
            $boards = MockBoard::whereIn('program', $def['variants'])->get();
            $progTakers = 0;
            $progPassed = 0;
            $progScoreSum = 0;
            $progBoardsCount = 0;

            foreach ($boards as $board) {
                $postTestPhaseIds = $board->phases()->postTests()->pluck('id');
                if ($postTestPhaseIds->isEmpty()) {
                    continue;
                }

                $progBoardsCount++;

                $attempts = MockBoardAttempt::whereIn('mock_board_phase_id', $postTestPhaseIds)
                    ->whereNotNull('percentage')
                    ->get()
                    ->groupBy('user_id');

                $passingThreshold = $board->passing_percentage ?? 75;

                foreach ($attempts as $userId => $userAttempts) {
                    $bestScore = (float) $userAttempts->max('percentage');
                    $isPassed = $bestScore >= $passingThreshold;

                    $progTakers++;
                    $progScoreSum += $bestScore;
                    if ($isPassed) {
                        $progPassed++;
                    }
                }
            }

            $progPassingRate = $progTakers > 0 ? round(($progPassed / $progTakers) * 100, 1) : 0.0;
            $progAvgScore = $progTakers > 0 ? round($progScoreSum / $progTakers, 1) : 0.0;

            $byProgram[$key] = [
                'program_key' => $key,
                'label' => $def['label'],
                'passing_rate' => $progPassingRate,
                'students_attempted' => $progTakers,
                'students_passed' => $progPassed,
                'boards_count' => $progBoardsCount,
                'average_score' => $progAvgScore,
            ];

            $totalTakersAll += $progTakers;
            $totalPassedAll += $progPassed;
            $totalScoreSumAll += $progScoreSum;
            $totalBoardsAll += $progBoardsCount;
        }

        $overallPassingRate = $totalTakersAll > 0 ? round(($totalPassedAll / $totalTakersAll) * 100, 1) : 0.0;
        $overallAvgScore = $totalTakersAll > 0 ? round($totalScoreSumAll / $totalTakersAll, 1) : 0.0;

        return [
            'overall' => [
                'passing_rate' => $overallPassingRate,
                'students_attempted' => $totalTakersAll,
                'students_passed' => $totalPassedAll,
                'boards_count' => $totalBoardsAll,
                'average_score' => $overallAvgScore,
            ],
            'by_program' => $byProgram,
        ];
    }
}
