<?php

namespace App\Services\ItemAnalysis;

use App\Models\ItemAnalysisResult;
use App\Models\Module;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\TestAnalysisResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ItemAnalysisService
{
    /**
     * Compute comprehensive psychometric item analysis and test reliability metrics.
     */
    public function analyzeQuiz(int $moduleId, ?string $quizStage = null, bool $persist = true): array
    {
        $module = Module::with(['quizQuestions'])->findOrFail($moduleId);

        // 1. Fetch completed attempts for students only
        $baseAttemptsQuery = QuizAttempt::query()
            ->where('module_id', $moduleId)
            ->where(function ($q) {
                $q->where('status', 'completed')
                    ->orWhereNotNull('completed_at')
                    ->orWhereNotNull('score');
            })
            ->whereHas('user', function ($q) {
                $q->where('role', 'student');
            });

        if ($quizStage !== null && $quizStage !== '') {
            $allAttempts = (clone $baseAttemptsQuery)->where('quiz_stage', $quizStage)->get();
            if ($allAttempts->isEmpty()) {
                $allAttempts = (clone $baseAttemptsQuery)->where(function ($q) {
                    $q->whereNull('quiz_stage')->orWhere('quiz_stage', '');
                })->get();
            }
            if ($allAttempts->isEmpty()) {
                $allAttempts = (clone $baseAttemptsQuery)->get();
            }
        } else {
            $allAttempts = (clone $baseAttemptsQuery)->where(function ($q) {
                $q->whereNull('quiz_stage')->orWhere('quiz_stage', '');
            })->get();
            if ($allAttempts->isEmpty()) {
                $allAttempts = (clone $baseAttemptsQuery)->get();
            }
        }

        // Deduplicate attempts per student according to configured policy
        $policy = config('item_analysis.attempt_policy', 'first');
        $selectedAttempts = $this->deduplicateAttempts($allAttempts, $policy);
        $examineeCount = $selectedAttempts->count();

        // 2. Fetch active questions for the module and stage
        $questionsQuery = QuizQuestion::query()
            ->where('module_id', $moduleId);

        if ($quizStage !== null && $quizStage !== '') {
            $questions = (clone $questionsQuery)->where('quiz_stage', $quizStage)->orderBy('order')->orderBy('id')->get();
            if ($questions->isEmpty()) {
                $questions = (clone $questionsQuery)->where(function ($q) {
                    $q->whereNull('quiz_stage')->orWhere('quiz_stage', '');
                })->orderBy('order')->orderBy('id')->get();
            }
            if ($questions->isEmpty()) {
                $questions = (clone $questionsQuery)->orderBy('order')->orderBy('id')->get();
            }
        } else {
            $questions = (clone $questionsQuery)->where(function ($q) {
                $q->whereNull('quiz_stage')->orWhere('quiz_stage', '');
            })->orderBy('order')->orderBy('id')->get();
            if ($questions->isEmpty()) {
                $questions = (clone $questionsQuery)->orderBy('order')->orderBy('id')->get();
            }
        }

        $itemCount = $questions->count();
        $now = Carbon::now();

        if ($examineeCount === 0 || $itemCount === 0) {
            $emptyResult = [
                'test' => [
                    'module_id' => $moduleId,
                    'quiz_stage' => $quizStage,
                    'n_examinees' => $examineeCount,
                    'n_items' => $itemCount,
                    'mean_score' => 0.0,
                    'sd_score' => 0.0,
                    'score_variance' => 0.0,
                    'kr20' => null,
                    'kr20_interpretation' => 'No completed attempts yet',
                    'mean_p' => null,
                    'mean_d' => null,
                    'label_accuracy' => null,
                    'mean_p_by_label' => [],
                    'verdict_counts' => ['retain' => 0, 'revise' => 0, 'reject' => 0, 'insufficient' => 0],
                    'analyzed_at' => $now,
                ],
                'items' => [],
            ];

            return $emptyResult;
        }

        // 3. Load all student answers in a single query
        $attemptIds = $selectedAttempts->pluck('id')->all();
        $answers = QuizAnswer::query()
            ->whereIn('attempt_id', $attemptIds)
            ->get()
            ->groupBy('attempt_id');

        // 4. Construct response matrix X[attempt_id][question_id] and choice matrix C[attempt_id][question_id]
        $matrixX = [];
        $matrixC = [];
        $totalScores = [];

        foreach ($selectedAttempts as $attempt) {
            $attId = $attempt->id;
            $attAnswers = $answers->get($attId, collect())->keyBy('question_id');
            $scoreSum = 0;

            foreach ($questions as $q) {
                $qId = $q->id;
                $correctOpt = strtoupper(trim((string) $q->correct_option));
                $ans = $attAnswers->get($qId);

                if ($ans) {
                    $rawSelected = $ans->selected_option ?? $ans->answer ?? null;
                    $selectedOpt = $rawSelected !== null ? strtoupper(trim((string) $rawSelected)) : null;
                    $isCorrect = (bool) ($ans->is_correct ?? ($selectedOpt === $correctOpt));
                    $val = $isCorrect ? 1 : 0;
                    $matrixX[$attId][$qId] = $val;
                    $matrixC[$attId][$qId] = $selectedOpt;
                    $scoreSum += $val;
                } else {
                    $matrixX[$attId][$qId] = 0;
                    $matrixC[$attId][$qId] = null;
                }
            }

            $totalScores[$attId] = $scoreSum;
        }

        // 5. Form Upper and Lower 27% groups (Kelley, 1939)
        $groupFraction = (float) config('item_analysis.group_fraction', 0.27);
        $g = max(1, (int) round($groupFraction * $examineeCount));

        // Sort students descending by total score, tie-breaking by user_id for determinism
        $sortedAttemptIds = $selectedAttempts->sortBy(function ($att) use ($totalScores) {
            return [-$totalScores[$att->id], $att->user_id];
        })->pluck('id')->values()->all();

        $upperGroup = array_slice($sortedAttemptIds, 0, $g);
        $lowerGroup = array_slice($sortedAttemptIds, -$g, $g);

        // 6. Calculate item-level metrics
        $itemsData = [];
        $pValues = [];
        $dValues = [];
        $labelMatchesCount = 0;
        $labeledItemsCount = 0;
        $pByLabelMap = [];
        $verdictCounts = ['retain' => 0, 'revise' => 0, 'reject' => 0, 'insufficient' => 0];

        $confidence = $this->resolveConfidence($examineeCount);

        foreach ($questions as $q) {
            $qId = $q->id;
            $correctOpt = strtoupper(trim((string) $q->correct_option));
            $aiLabel = $q->difficulty ? ucfirst(strtolower(trim((string) $q->difficulty))) : null;

            // Total correct count
            $nCorrect = 0;
            foreach ($selectedAttempts as $att) {
                $nCorrect += $matrixX[$att->id][$qId];
            }

            // p index (Difficulty)
            $pIndex = round($nCorrect / $examineeCount, 4);
            $pValues[] = $pIndex;

            // Upper & Lower group correct counts
            $rUpper = 0;
            foreach ($upperGroup as $uAttId) {
                $rUpper += $matrixX[$uAttId][$qId];
            }

            $rLower = 0;
            foreach ($lowerGroup as $lAttId) {
                $rLower += $matrixX[$lAttId][$qId];
            }

            $pUpper = $rUpper / $g;
            $pLower = $rLower / $g;
            $dIndex = round($pUpper - $pLower, 4);
            $dValues[] = $dIndex;

            // Point-biserial correlation (corrected rest-score)
            $pointBiserial = $this->calculatePointBiserial($selectedAttempts, $matrixX, $totalScores, $qId, $pIndex);

            // Distractor analysis & Option stats
            $distractorAnalysis = $this->analyzeDistractors($selectedAttempts, $matrixC, $upperGroup, $lowerGroup, $q, $examineeCount);
            $optionStats = $distractorAnalysis['option_stats'];
            $de = $distractorAnalysis['de'];
            $problemDistractors = $distractorAnalysis['problem_distractors'];

            // Categorical interpretations
            $diffInterp = $this->interpretDifficulty($pIndex);
            $discInterp = $this->interpretDiscrimination($dIndex);
            $empiricalBand = $this->resolveEmpiricalBand($pIndex);

            $labelMatches = null;
            if ($aiLabel) {
                $labeledItemsCount++;
                $normalizedAi = $this->normalizeDifficultyLabel($aiLabel);
                $normalizedEmpirical = $this->normalizeDifficultyLabel($empiricalBand);
                $labelMatches = ($normalizedAi !== null && $normalizedAi === $normalizedEmpirical);
                if ($labelMatches) {
                    $labelMatchesCount++;
                }
                $pByLabelMap[$aiLabel][] = $pIndex;
            }

            // Flags
            $flags = [];
            if ($dIndex < 0) {
                $flags[] = 'negative_d';
            }
            foreach ($problemDistractors as $probOpt) {
                $flags[] = 'problem_distractor:'.$probOpt;
            }
            if ($pIndex >= 1.0) {
                $flags[] = 'all_correct';
            } elseif ($pIndex <= 0.0) {
                $flags[] = 'all_incorrect';
            }

            // Decision verdict
            $verdict = $this->resolveItemVerdict($pIndex, $dIndex, $flags, $confidence);
            $verdictCounts[$verdict] = ($verdictCounts[$verdict] ?? 0) + 1;

            $itemsData[] = [
                'module_id' => $moduleId,
                'question_id' => $qId,
                'quiz_stage' => $quizStage,
                'question_text' => $q->question_text,
                'correct_option' => $correctOpt,
                'ai_difficulty_label' => $aiLabel,
                'n_examinees' => $examineeCount,
                'n_correct' => $nCorrect,
                'p_index' => $pIndex,
                'd_index' => $dIndex,
                'point_biserial' => $pointBiserial !== null ? round($pointBiserial, 4) : null,
                'option_stats' => $optionStats,
                'distractor_efficiency' => $de !== null ? round($de, 2) : null,
                'difficulty_interpretation' => $diffInterp,
                'discrimination_interpretation' => $discInterp,
                'empirical_band' => $empiricalBand,
                'label_matches' => $labelMatches,
                'verdict' => $verdict,
                'flags' => $flags,
                'confidence' => $confidence,
                'analyzed_at' => $now,
            ];
        }

        // 7. Test-level metrics & KR-20 reliability
        $scoresList = array_values($totalScores);
        $meanScore = count($scoresList) > 0 ? (array_sum($scoresList) / count($scoresList)) : 0.0;

        $variance = 0.0;
        if (count($scoresList) > 0) {
            $sumSqDiff = 0.0;
            foreach ($scoresList as $s) {
                $sumSqDiff += pow($s - $meanScore, 2);
            }
            $variance = $sumSqDiff / count($scoresList); // Population variance
        }
        $sdScore = sqrt($variance);

        $kr20 = null;
        $kr20Interp = 'Not computable (insufficient items)';
        if ($itemCount >= 2) {
            if ($variance <= 0.00001) {
                $kr20Interp = 'Not computable (no score variance)';
            } else {
                $sumPq = 0.0;
                foreach ($pValues as $pVal) {
                    $sumPq += ($pVal * (1.0 - $pVal));
                }
                $kr20Val = ($itemCount / ($itemCount - 1)) * (1.0 - ($sumPq / $variance));
                $kr20 = round($kr20Val, 4);
                $kr20Interp = $this->interpretKr20($kr20);
            }
        }

        $meanP = count($pValues) > 0 ? round(array_sum($pValues) / count($pValues), 4) : null;
        $meanD = count($dValues) > 0 ? round(array_sum($dValues) / count($dValues), 4) : null;
        $labelAccuracy = $labeledItemsCount > 0 ? round(($labelMatchesCount / $labeledItemsCount) * 100, 2) : null;

        $meanPByLabel = [];
        foreach ($pByLabelMap as $lbl => $arr) {
            $meanPByLabel[$lbl] = count($arr) > 0 ? round(array_sum($arr) / count($arr), 4) : null;
        }

        $testData = [
            'module_id' => $moduleId,
            'quiz_stage' => $quizStage,
            'n_examinees' => $examineeCount,
            'n_items' => $itemCount,
            'mean_score' => round($meanScore, 2),
            'sd_score' => round($sdScore, 2),
            'score_variance' => round($variance, 4),
            'kr20' => $kr20,
            'kr20_interpretation' => $kr20Interp,
            'mean_p' => $meanP,
            'mean_d' => $meanD,
            'label_accuracy' => $labelAccuracy,
            'mean_p_by_label' => $meanPByLabel,
            'verdict_counts' => $verdictCounts,
            'analyzed_at' => $now,
        ];

        // 8. Persist results in DB if requested
        if ($persist) {
            DB::transaction(function () use ($moduleId, $quizStage, $testData, $itemsData) {
                // Remove older runs for this module & stage
                $testQuery = TestAnalysisResult::where('module_id', $moduleId);
                $itemQuery = ItemAnalysisResult::where('module_id', $moduleId);

                if ($quizStage !== null && $quizStage !== '') {
                    $testQuery->where('quiz_stage', $quizStage);
                    $itemQuery->where('quiz_stage', $quizStage);
                } else {
                    $testQuery->where(function ($q) {
                        $q->whereNull('quiz_stage')->orWhere('quiz_stage', '');
                    });
                    $itemQuery->where(function ($q) {
                        $q->whereNull('quiz_stage')->orWhere('quiz_stage', '');
                    });
                }

                $testQuery->delete();
                $itemQuery->delete();

                TestAnalysisResult::create($testData);

                foreach ($itemsData as $itemRow) {
                    ItemAnalysisResult::create($itemRow);
                }
            });
        }

        return [
            'test' => $testData,
            'items' => $itemsData,
        ];
    }

    /**
     * Deduplicate attempts to one attempt per student according to policy.
     */
    private function deduplicateAttempts($attempts, string $policy)
    {
        return $attempts->groupBy('user_id')->map(function ($userAttempts) use ($policy) {
            if ($policy === 'best') {
                return $userAttempts->sortByDesc('score')->first();
            }

            if ($policy === 'latest') {
                return $userAttempts->sortByDesc(function ($att) {
                    return $att->completed_at ? $att->completed_at->timestamp : $att->id;
                })->first();
            }

            // Default: 'first'
            return $userAttempts->sortBy(function ($att) {
                return $att->started_at ? $att->started_at->timestamp : $att->id;
            })->first();
        })->values();
    }

    /**
     * Compute point-biserial correlation using rest-score.
     */
    private function calculatePointBiserial($attempts, array $matrixX, array $totalScores, int $qId, float $p): ?float
    {
        if ($p <= 0.0 || $p >= 1.0) {
            return null;
        }

        $restScores1 = [];
        $restScores0 = [];
        $allRestScores = [];

        foreach ($attempts as $att) {
            $isCorrect = $matrixX[$att->id][$qId];
            $rest = $totalScores[$att->id] - $isCorrect;
            $allRestScores[] = $rest;

            if ($isCorrect === 1) {
                $restScores1[] = $rest;
            } else {
                $restScores0[] = $rest;
            }
        }

        if (count($restScores1) === 0 || count($restScores0) === 0) {
            return null;
        }

        $mean1 = array_sum($restScores1) / count($restScores1);
        $mean0 = array_sum($restScores0) / count($restScores0);
        $overallMean = array_sum($allRestScores) / count($allRestScores);

        $sumSqDiff = 0.0;
        foreach ($allRestScores as $r) {
            $sumSqDiff += pow($r - $overallMean, 2);
        }
        $varianceX = $sumSqDiff / count($allRestScores);
        $sdX = sqrt($varianceX);

        if ($sdX <= 0.00001) {
            return null;
        }

        return (($mean1 - $mean0) / $sdX) * sqrt($p * (1.0 - $p));
    }

    /**
     * Compute distractor selection rates and distractor efficiency (DE).
     */
    private function analyzeDistractors($attempts, array $matrixC, array $upperGroup, array $lowerGroup, QuizQuestion $q, int $totalExaminees): array
    {
        $correctOpt = strtoupper(trim((string) $q->correct_option));
        $rawOptions = $q->options ?? [];
        $optionKeys = [];

        if (is_array($rawOptions)) {
            foreach ($rawOptions as $k => $v) {
                $key = is_string($k) && strlen($k) <= 2 ? strtoupper(trim($k)) : strtoupper(trim((string) $v));
                if ($key !== '') {
                    $optionKeys[] = $key;
                }
            }
        }

        // Standard fallback if options are indexed 0..3 or letters
        if (empty($optionKeys)) {
            $optionKeys = ['A', 'B', 'C', 'D'];
        }
        $optionKeys = array_unique($optionKeys);

        // Include correct option in keys if not present
        if (! in_array($correctOpt, $optionKeys, true)) {
            $optionKeys[] = $correctOpt;
        }

        // Option stats tallies
        $stats = [];
        foreach ($optionKeys as $k) {
            $stats[$k] = ['total' => 0, 'upper' => 0, 'lower' => 0, 'rate' => 0.0];
        }
        $stats['omitted'] = ['total' => 0, 'upper' => 0, 'lower' => 0, 'rate' => 0.0];

        $upperSet = array_flip($upperGroup);
        $lowerSet = array_flip($lowerGroup);

        foreach ($attempts as $att) {
            $attId = $att->id;
            $chosen = $matrixC[$attId][$q->id];

            if ($chosen === null || $chosen === '') {
                $stats['omitted']['total']++;
                if (isset($upperSet[$attId])) {
                    $stats['omitted']['upper']++;
                }
                if (isset($lowerSet[$attId])) {
                    $stats['omitted']['lower']++;
                }
            } else {
                $key = strtoupper(trim((string) $chosen));
                if (! isset($stats[$key])) {
                    $stats[$key] = ['total' => 0, 'upper' => 0, 'lower' => 0, 'rate' => 0.0];
                }
                $stats[$key]['total']++;
                if (isset($upperSet[$attId])) {
                    $stats[$key]['upper']++;
                }
                if (isset($lowerSet[$attId])) {
                    $stats[$key]['lower']++;
                }
            }
        }

        // Calculate rate
        foreach ($stats as $k => &$row) {
            $row['rate'] = $totalExaminees > 0 ? round($row['total'] / $totalExaminees, 4) : 0.0;
        }
        unset($row);

        // Check distractors (options != correct_option)
        $minRate = (float) config('item_analysis.functional_distractor_min_rate', 0.05);
        $functionalCount = 0;
        $totalDistractors = 0;
        $problemDistractors = [];

        foreach ($stats as $optKey => $stat) {
            if ($optKey === 'omitted' || $optKey === $correctOpt) {
                continue;
            }

            $totalDistractors++;
            $rate = $stat['rate'];
            $uCount = $stat['upper'];
            $lCount = $stat['lower'];

            // Functional distractor: chosen by >= 5%, and chosen more by Lower group than Upper group
            if ($rate >= $minRate && $lCount >= $uCount) {
                $functionalCount++;
            }

            // Problem distractor: chosen more by Upper group than Lower group with rate >= 5%
            if ($rate >= $minRate && $uCount > $lCount) {
                $problemDistractors[] = $optKey;
            }
        }

        $de = $totalDistractors > 0 ? round(($functionalCount / $totalDistractors) * 100, 2) : 100.0;

        return [
            'option_stats' => $stats,
            'de' => $de,
            'problem_distractors' => $problemDistractors,
        ];
    }

    private function interpretDifficulty(float $p): string
    {
        $bands = config('item_analysis.p_bands', [
            ['min' => 0.00, 'max' => 0.20, 'label' => 'Very Difficult'],
            ['min' => 0.21, 'max' => 0.40, 'label' => 'Difficult'],
            ['min' => 0.41, 'max' => 0.60, 'label' => 'Average'],
            ['min' => 0.61, 'max' => 0.80, 'label' => 'Easy'],
            ['min' => 0.81, 'max' => 1.00, 'label' => 'Very Easy'],
        ]);

        foreach ($bands as $band) {
            if ($p >= $band['min'] && $p <= $band['max']) {
                return $band['label'];
            }
        }

        return $p > 0.80 ? 'Very Easy' : 'Very Difficult';
    }

    private function interpretDiscrimination(float $d): string
    {
        if ($d >= 0.40) {
            return 'Very good';
        }
        if ($d >= 0.30) {
            return 'Reasonably good';
        }
        if ($d >= 0.20) {
            return 'Marginal';
        }
        if ($d >= 0.00) {
            return 'Poor';
        }

        return 'Negative';
    }

    private function resolveEmpiricalBand(float $p): string
    {
        $difficultMax = (float) config('item_analysis.empirical_bands.difficult_max', 0.40);
        $easyMin = (float) config('item_analysis.empirical_bands.easy_min', 0.71);

        if ($p <= $difficultMax) {
            return 'Difficult';
        }
        if ($p >= $easyMin) {
            return 'Easy';
        }

        return 'Average';
    }

    /**
     * Normalize AI difficulty labels and empirical bands for consistent comparison.
     */
    public function normalizeDifficultyLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $clean = strtolower(trim($label));

        if (in_array($clean, ['easy', 'very easy', 'basic'], true)) {
            return 'easy';
        }

        if (in_array($clean, ['normal', 'average', 'medium', 'moderate'], true)) {
            return 'average';
        }

        if (in_array($clean, ['hard', 'difficult', 'very difficult', 'challenging'], true)) {
            return 'difficult';
        }

        return $clean;
    }

    private function resolveConfidence(int $n): string
    {
        $minN = (int) config('item_analysis.min_n_insufficient', 10);
        $fullN = (int) config('item_analysis.min_n_full_confidence', 30);

        if ($n < $minN) {
            return 'insufficient';
        }
        if ($n < $fullN) {
            return 'low';
        }

        return 'full';
    }

    private function resolveItemVerdict(float $p, float $d, array $flags, string $confidence): string
    {
        if ($confidence === 'insufficient') {
            return 'insufficient';
        }

        // Negative D is a critical red flag (wrong answer key or flawed item)
        if ($d < 0) {
            return 'reject';
        }

        // Combine standard matrix
        $baseVerdict = 'retain';
        if ($p >= 0.21 && $p <= 0.80) {
            if ($d >= 0.30) {
                $baseVerdict = 'retain';
            } elseif ($d >= 0.20) {
                $baseVerdict = 'revise';
            } else {
                $baseVerdict = 'reject';
            }
        } else {
            // Extreme difficulty (p < 0.21 or p > 0.80)
            if ($d >= 0.20) {
                $baseVerdict = 'revise';
            } else {
                $baseVerdict = 'reject';
            }
        }

        // Override: Any problem distractor bumps 'retain' to 'revise'
        $hasProblemDistractor = false;
        foreach ($flags as $f) {
            if (str_starts_with($f, 'problem_distractor:')) {
                $hasProblemDistractor = true;
                break;
            }
        }

        if ($hasProblemDistractor && $baseVerdict === 'retain') {
            $baseVerdict = 'revise';
        }

        return $baseVerdict;
    }

    private function interpretKr20(?float $kr20): string
    {
        if ($kr20 === null) {
            return 'Not computable';
        }
        if ($kr20 >= 0.90) {
            return 'Excellent';
        }
        if ($kr20 >= 0.80) {
            return 'Good';
        }
        if ($kr20 >= 0.70) {
            return 'Acceptable';
        }
        if ($kr20 >= 0.60) {
            return 'Questionable';
        }

        return 'Poor';
    }
}
