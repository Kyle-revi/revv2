<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAttemptGrant;
use App\Models\ClassModel;
use App\Models\MockBoardAttempt;
use App\Models\MockBoardPhase;
use App\Models\Module;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptSnapshot;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\AiSettingsResolver;
use App\Services\CloudflareAI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuizController extends Controller
{
    /**
     * Resolve which lecture stage a request refers to. NULL means "not a
     * lecture stage" — ordinary standalone quiz or mock-board-phase quiz,
     * preserving all pre-existing behavior exactly as it was before
     * quiz_stage existed. Every quiz_questions/quiz_attempts/
     * quiz_attempt_snapshots lookup in this controller must key off this
     * value alongside module_id, or a post-test attempt will collide with
     * (and overwrite) the pre-test attempt for the same module.
     */
    private function resolveStage(Request $request): ?string
    {
        $stage = $request->input('quiz_stage', $request->query('quiz_stage'));

        return in_array($stage, ['pre_test', 'post_test'], true) ? $stage : null;
    }

    private function buildFallbackQuizInsights(?QuizAttempt $attempt, $answers): array
    {
        if (! $attempt) {
            return [
                'strong' => 'Your quiz result was shown, but the saved attempt details are not available yet.',
                'weak' => 'Detailed answer analysis could not be loaded for this attempt.',
                'recommendation' => 'Refresh once to retry loading insights.',
            ];
        }

        $answers = collect($answers ?? []);
        $correctAnswers = $answers->where('is_correct', true)->values();
        $wrongAnswers = $answers->where('is_correct', false)->values();

        $strong = 'You answered '.$attempt->score.' out of '.$attempt->total.' questions correctly.';
        if ($correctAnswers->isNotEmpty()) {
            $correctTopics = $correctAnswers->take(2)
                ->map(fn ($answer) => trim((string) data_get($answer, 'question.question_text', '')))
                ->filter()->map(fn ($text) => Str::limit($text, 80))->implode(' | ');
            if ($correctTopics !== '') {
                $strong .= ' Strong items: '.$correctTopics.'.';
            }
        }

        $weak = 'No major weak areas detected.';
        if ($wrongAnswers->isNotEmpty()) {
            $weakTopics = $wrongAnswers->take(2)
                ->map(fn ($answer) => trim((string) data_get($answer, 'question.question_text', '')))
                ->filter()->map(fn ($text) => Str::limit($text, 80))->implode(' | ');
            $weak = $weakTopics !== '' ? 'Review: '.$weakTopics.'.' : 'Review missed items.';
        }

        $recommendation = match (true) {
            $attempt->percentage >= 85 => 'Keep the pace and review the missed items once for retention.',
            $attempt->percentage >= 50 => 'Review the incorrect questions and revisit the related lesson sections before the next attempt.',
            default => 'Revisit the module content first, then retake the quiz.',
        };

        return ['strong' => $strong, 'weak' => $weak, 'recommendation' => $recommendation];
    }

    public function getQuestions(Request $request, Module $module)
    {
        $stage = $this->resolveStage($request);

        $query = QuizQuestion::where('module_id', $module->id);

        if ($stage !== null) {
            // Check questions explicitly assigned to this quiz stage
            $questions = (clone $query)->where('quiz_stage', $stage)->orderBy('order')->get();

            // Fallback: If no questions found with this stage (e.g. Test Bank imports or dedicated pre/post test modules),
            // get unassigned (quiz_stage is null) questions or all module questions.
            if ($questions->isEmpty()) {
                $questions = (clone $query)->whereNull('quiz_stage')->orderBy('order')->get();
            }

            if ($questions->isEmpty()) {
                $questions = (clone $query)->orderBy('order')->get();
            }
        } else {
            // First look for quiz_stage = null
            $questions = (clone $query)->whereNull('quiz_stage')->orderBy('order')->get();

            // Fallback to module's own configured quiz_stage if set
            if ($questions->isEmpty() && ! empty($module->quiz_stage)) {
                $questions = (clone $query)->where('quiz_stage', $module->quiz_stage)->orderBy('order')->get();
            }

            // Fallback to any questions attached to this module
            if ($questions->isEmpty()) {
                $questions = (clone $query)->orderBy('order')->get();
            }
        }

        if ($questions->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No questions found in this assessment.'], 404);
        }

        return response()->json([
            'success' => true,
            'questions' => $questions->map(fn ($q) => [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'options' => is_array($q->options) ? $q->options : (json_decode((string) $q->options, true) ?? []),
                'correct' => $q->correct_option,
            ]),
            'time_limit' => $module->time_limit ?? 0,
        ]);
    }

    public function generateInsights(Request $request, Module $module)
    {
        $resolver = app(AiSettingsResolver::class);
        $class = $module->class;
        if ($class) {
            $classSettings = $resolver->getClassSettings($class);
            if (! ($classSettings['features']['quiz_insights_enabled'] ?? true)) {
                return response()->json(['success' => false, 'message' => 'AI Quiz Insights is disabled for this class.'], 403);
            }
        }

        $user = Auth::user();
        $stage = $this->resolveStage($request);

        $attemptId = $request->input('attempt_id');
        $attempt = null;

        if (! empty($attemptId)) {
            $attempt = QuizAttempt::where('id', $attemptId)
                ->where('user_id', $user->id)
                ->where('module_id', $module->id)
                ->first();
        }

        if (! $attempt) {
            // Fallback lang para sa mga lumang cached frontend na hindi pa
            // nagpapasa ng attempt_id.
            $mockPhase = MockBoardPhase::where('module_id', $module->id)->first();

            $attemptQuery = QuizAttempt::where('user_id', $user->id)
                ->where('module_id', $module->id)
                ->where('mock_board_id', $mockPhase?->mock_board_id);

            if ($stage !== null) {
                $attemptQuery->where('quiz_stage', $stage);
            }

            $attempt = (clone $attemptQuery)->whereNotNull('completed_at')->latest('completed_at')->first()
                ?? $attemptQuery->latest('id')->first();
        }

        if (! $attempt) {
            $fallback = $this->buildFallbackQuizInsights(null, []);

            return response()->json([
                'success' => true,
                'strong' => $fallback['strong'],
                'weak' => $fallback['weak'],
                'recommendation' => $fallback['recommendation'],
            ]);
        }

        if ($attempt->ai_strong !== null) {
            return response()->json([
                'success' => true,
                'strong' => $attempt->ai_strong,
                'weak' => $attempt->ai_weak,
                'recommendation' => $attempt->ai_recommendation,
            ]);
        }

        $answers = QuizAnswer::where('attempt_id', $attempt->id)->with('question')->get();
        $fallback = $this->buildFallbackQuizInsights($attempt, $answers);

        try {
            $resolver = app(AiSettingsResolver::class);
            $ai = app(CloudflareAI::class);
            $answersContext = '';
            foreach ($answers as $a) {
                $status = $a->is_correct ? 'Correct' : 'Wrong';
                $answersContext .= "- Q: {$a->question->question_text}\n Answer: {$a->selected_option} -> {$status}\n";
            }

            $userPrompt = $resolver->renderTemplate($resolver->getPromptTemplate('quiz_insights', 'user_template'), [
                'score' => (string) $attempt->percentage,
                'module_title' => (string) $module->title,
                'answers_context' => trim($answersContext),
            ]);

            $result = $ai->run($resolver->getModel(), [
                'messages' => [
                    ['role' => 'system', 'content' => $resolver->getPromptTemplate('quiz_insights', 'system')],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => $resolver->getMaxTokens(),
                'temperature' => 0.6,
            ]);

            // Always persist the fallback insights regardless of whether the
            // AI call returned usable text — an empty/blank AI response should
            // not leave ai_strong/ai_weak/ai_recommendation stuck at null.
            $attempt->update(['ai_strong' => $fallback['strong'], 'ai_weak' => $fallback['weak'], 'ai_recommendation' => $fallback['recommendation']]);
        } catch (\Exception $e) {
            $attempt->update(['ai_strong' => $fallback['strong'], 'ai_weak' => $fallback['weak'], 'ai_recommendation' => $fallback['recommendation']]);
        }

        return response()->json(['success' => true, 'strong' => $attempt->ai_strong, 'weak' => $attempt->ai_weak, 'recommendation' => $attempt->ai_recommendation]);
    }

    /**
     * Kunin ang totoong pinapayagang bilang ng attempts para sa estudyanteng
     * ito sa module na ito = base max_attempts ng module + kung may extra
     * grant na ibinigay ng teacher. Isa lang ang max_attempts per module
     * (hindi pa hinati per-stage) kaya pareho ang limitasyon ng pre-test at
     * post-test kung parehong is_formal_assessment.
     */
    private function getAllowedAttempts(Module $module, int $userId): int
    {
        return $module->allowedAttemptsFor($userId);
    }

    /**
     * I-mark ang simula ng pagkuha ng quiz — dito gagawa ng record kahit
     * hindi pa nag-a-answer ang estudyante, para hindi mawala ang bakas
     * kapag nag-back/nag-abandon sila bago matapos.
     */
    public function startAttempt(Request $request, Module $module)
    {
        $user = Auth::user();
        $stage = $this->resolveStage($request);

        if ($module->isUpcoming()) {
            return response()->json([
                'success' => false,
                'message' => 'This quiz/assessment is not yet open. It will be available on '.($module->available_at?->format('M d, Y g:i A') ?? 'the scheduled date').'.',
            ], 403);
        }

        if (! ($module->is_active ?? true)) {
            return response()->json([
                'success' => false,
                'message' => 'This quiz/assessment is currently inactive.',
            ], 403);
        }

        if ($module->isOverdue()) {
            return response()->json([
                'success' => false,
                'message' => 'This quiz/assessment is past its due date and is no longer available.',
            ], 403);
        }

        $mockPhase = MockBoardPhase::where('module_id', $module->id)->first();
        $mockBoardId = $mockPhase?->mock_board_id;

        $attempt = QuizAttempt::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('mock_board_id', $mockBoardId)
            ->where('quiz_stage', $stage)
            ->first();

        // Kung "in_progress" ang naunang attempt, i-check muna kung lumagpas na
        // sa 1 minuto mula sa huling activity (updated_at, na na-touch() sa
        // bawat submitAnswer()). Kung lumagpas na, ituring itong "timed out":
        // markahan bilang 0/failed, at ituloy sa baba bilang bagong pagsubok.
        $treatAsFreshStart = false;

        if ($attempt && $attempt->status === 'in_progress') {
            $minutesSinceActivity = $attempt->updated_at->diffInMinutes(now());

            if ($minutesSinceActivity > 1) {
                QuizAnswer::where('attempt_id', $attempt->id)->delete();

                $attempt->update([
                    'score' => 0,
                    'total' => $attempt->total ?: $module->quizQuestions()->count(),
                    'percentage' => 0,
                    'passed' => false,
                    'status' => 'completed',
                    'completed_at' => now(),
                    'ai_strong' => null,
                    'ai_weak' => null,
                    'ai_recommendation' => null,
                ]);

                $treatAsFreshStart = true;
            }
        }

        if (! $attempt) {
            // Unang pagkakataon kukuha — palaging pinapayagan, dahil 1 pa lang ang gagamitin.
            $attempt = QuizAttempt::create([
                'user_id' => $user->id,
                'module_id' => $module->id,
                'quiz_stage' => $stage,
                'mock_board_id' => $mockBoardId,
                'attempt_count' => 1,
                'score' => 0,
                'total' => 0,
                'percentage' => 0,
                'passed' => false,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        } elseif ($attempt->status === 'in_progress' && ! $treatAsFreshStart) {
            // Nag-abandon dati (halimbawa back button) pero loob pa ng 1 minuto —
            // pinatuloy, huwag na dagdagan ang count
            $attempt->update(['started_at' => now()]);
        } else {
            // Natapos na dati (o kakatapos lang dahil sa timeout sa itaas) —
            // ito ay bagong pagsubok. I-enforce ang attempt limit dito lamang
            // para sa formal assessments (Pre-Test, Post-Test, Mock Board).
            // Ang mga practice modules (is_formal_assessment = false) ay walang limitasyon.
            if ($module->is_formal_assessment) {
                $allowed = $this->getAllowedAttempts($module, $user->id);
                $used = $attempt->attempt_count ?? 1;

                if ($used >= $allowed) {
                    return response()->json([
                        'success' => false,
                        'message' => $treatAsFreshStart
                            ? 'Your previous attempt timed out (exceeded 1 minute before resuming) and was marked with a score of 0. You have reached your maximum allowed attempts. Please contact your instructor if you need an additional attempt.'
                            : 'You have reached the maximum allowed attempts for this assessment. Please contact your instructor if you need an additional attempt.',
                        'attempts_used' => $used,
                        'attempts_allowed' => $allowed,
                        'timed_out' => $treatAsFreshStart,
                    ], 403);
                }
            }

            $attempt->update([
                'attempt_count' => ($attempt->attempt_count ?? 0) + 1,
                'score' => 0,
                'total' => 0,
                'percentage' => 0,
                'passed' => false,
                'status' => 'in_progress',
                'started_at' => now(),
                'completed_at' => null,
                'ai_strong' => null,
                'ai_weak' => null,
                'ai_recommendation' => null,
            ]);

            // Burahin ang lumang sagot mula sa naunang pagsubok
            QuizAnswer::where('attempt_id', $attempt->id)->delete();
        }

        return response()->json([
            'success' => true,
            'attempt_count' => $attempt->attempt_count,
            'timed_out' => $treatAsFreshStart,
            'attempt_id' => $attempt->id,
        ]);
    }

    public function submitAnswer(Request $request, Module $module)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'question_id' => 'required|exists:quiz_questions,id',
            'selected_option' => 'required',
            'attempt_id' => 'nullable|integer|exists:quiz_attempts,id',
        ]);

        $stage = $this->resolveStage($request);
        $question = QuizQuestion::findOrFail($validated['question_id']);

        if ($question->module_id !== $module->id) {
            return response()->json(['success' => false, 'message' => 'This question does not belong to this module.'], 422);
        }

        $attempt = null;

        if (! empty($validated['attempt_id'])) {
            $attempt = QuizAttempt::where('id', $validated['attempt_id'])
                ->where('user_id', $user->id)
                ->where('module_id', $module->id)
                ->first();
        }

        if (! $attempt) {
            // Fallback lang ito para sa mga lumang cached frontend na hindi pa
            // nagpapasa ng attempt_id — dapat na dumaan dito ang mga bago.
            $mockPhase = MockBoardPhase::where('module_id', $module->id)->first();

            $attempt = QuizAttempt::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'module_id' => $module->id,
                    'mock_board_id' => $mockPhase?->mock_board_id,
                    'quiz_stage' => $stage,
                ],
                [
                    'attempt_count' => 1,
                    'score' => 0,
                    'total' => 0,
                    'percentage' => 0,
                    'passed' => false,
                    'status' => 'in_progress',
                    'started_at' => now(),
                ]
            );
        }

        $selected = strtoupper(trim((string) $validated['selected_option']));
        $correct = strtoupper(trim((string) $question->correct_option));
        $isCorrect = ($selected === $correct);

        QuizAnswer::updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $question->id],
            ['selected_option' => $selected, 'is_correct' => $isCorrect]
        );

        // I-update ang timestamp ng attempt bilang bakas ng huling activity —
        // ginagamit ito ng startAttempt() para malaman kung kailan huling
        // gumalaw ang estudyante (para sa 1-minute grace period sa pag-resume).
        $attempt->touch();

        return response()->json(['success' => true, 'isCorrect' => $isCorrect]);
    }

    public function submitQuiz(Request $request, Module $module)
    {
        $user = Auth::user();
        $stage = $this->resolveStage($request);

        return DB::transaction(function () use ($user, $module, $request, $stage) {
            $mockPhase = MockBoardPhase::where('module_id', $module->id)->first();
            $mockBoardId = $mockPhase?->mock_board_id;
            $phaseType = $mockPhase?->phase_type;

            $attemptId = $request->input('attempt_id');
            $attempt = null;

            if (! empty($attemptId)) {
                $attempt = QuizAttempt::where('id', $attemptId)
                    ->where('user_id', $user->id)
                    ->where('module_id', $module->id)
                    ->first();
            }

            if (! $attempt) {
                // Fallback lang ito para sa mga lumang cached frontend na hindi pa
                // nagpapasa ng attempt_id.
                $attempt = QuizAttempt::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'module_id' => $module->id,
                        'mock_board_id' => $mockBoardId,
                        'quiz_stage' => $stage,
                    ],
                    [
                        'attempt_count' => 1,
                        'score' => 0,
                        'total' => 0,
                        'percentage' => 0,
                        'passed' => false,
                        'status' => 'in_progress',
                        'started_at' => now(),
                    ]
                );
            }

            // Kung hindi pa nakatakda ang quiz_stage sa attempt na ito (e.g.
            // galing sa firstOrCreate na dumaan sa fallback path sa itaas
            // bago pa man magkaroon ng quiz_stage sa key), itakda ngayon.
            if ($attempt->quiz_stage === null && $stage !== null) {
                $attempt->quiz_stage = $stage;
            }

            $answers = QuizAnswer::where('attempt_id', $attempt->id)->get();
            $correctCount = $answers->where('is_correct', true)->count();
            $totalQuestions = $answers->count() ?: (int) $request->input('total', 0);

            if ($totalQuestions === 0) {
                $totalQuestions = QuizQuestion::where('module_id', $module->id)
                    ->where('quiz_stage', $stage)
                    ->count();
            }

            $percentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100) : 0;
            $passingThreshold = $module->passing_grade ?? config('quiz.default_passing_grade', 50);
            $isPassed = $percentage >= $passingThreshold;

            $attempt->update([
                'score' => $correctCount,
                'total' => $totalQuestions,
                'percentage' => $percentage,
                'passed' => $isPassed,
                'status' => 'completed',
                'completed_at' => now(),
                'ai_strong' => null,
            ]);

            // Bilang ng snapshot number base sa dati nang naitalang attempts,
            // hindi sa attempt_count ng QuizAttempt (para hindi ma-desync).
            $nextAttemptNumber = QuizAttemptSnapshot::where('user_id', $user->id)
                ->where('module_id', $module->id)
                ->where('mock_board_id', $mockBoardId)
                ->where('quiz_stage', $stage)
                ->max('attempt_number');
            $nextAttemptNumber = ($nextAttemptNumber ?? 0) + 1;

            QuizAttemptSnapshot::create([
                'user_id' => $user->id,
                'module_id' => $module->id,
                'quiz_stage' => $stage,
                'mock_board_id' => $mockBoardId,
                'phase_type' => $phaseType,
                'attempt_number' => $nextAttemptNumber,
                'score' => $correctCount,
                'total' => $totalQuestions,
                'percentage' => $percentage,
                'passed' => $isPassed,
                'started_at' => $attempt->started_at,
                'completed_at' => now(),
                'questions_snapshot' => $answers->map(function ($a) {
                    return [
                        'question_id' => $a->question_id,
                        'question_text' => $a->question->question_text ?? null,
                        'options' => $a->question->options ?? null,
                        'correct_option' => $a->question->correct_option ?? null,
                        'selected_option' => $a->selected_option,
                        'is_correct' => $a->is_correct,
                    ];
                })->values()->toArray(),
            ]);

            // SYNC TO MOCK BOARD ATTEMPTS (Para lumabas sa Teacher/Admin Analytics)
            // Hindi apektado ng quiz_stage — ang mock board phases ay hiwalay
            // na Modules pa rin, hindi lecture pre/post-test.
            //
            // IMPORTANT: MockBoardAttempt is the single cached row analytics and
            // the student's own results screen read from for a given
            // (user, mock_board_phase). Per product decision, an individual's
            // pass/fail — and therefore every "passing rate" rollup built on top
            // of this table (per-class, per-program, batch) — must be based on
            // the student's BEST score across all their attempts for that
            // phase, not just their most recent submission. So we only
            // overwrite the cached score/passed fields when this attempt
            // actually beats the previous best.
            //
            // Keyed by mock_board_phase_id (not phase_type) since a board can
            // now have multiple phases of the same phase_type (e.g. several
            // post-tests) — phase_type alone is no longer unique per board,
            // and using it as the key would collide two different post-test
            // phases' scores onto the same row.
            if ($mockPhase) {
                $passingGrade = 75;
                if ($mockPhase->mockBoard) {
                    $passingGrade = $mockPhase->mockBoard->passing_percentage ?? 75;
                }

                $existingMockBoardAttempt = MockBoardAttempt::where([
                    'user_id' => $user->id,
                    'mock_board_phase_id' => $mockPhase->id,
                ])->first();

                $isNewBestForMockBoard = ! $existingMockBoardAttempt
                    || $percentage > ($existingMockBoardAttempt->percentage ?? -1);

                $mockBoardAttemptValues = [
                    'mock_board_id' => $mockPhase->mock_board_id,
                    'phase_type' => $mockPhase->phase_type,
                    'attempt_count' => $existingMockBoardAttempt
                        ? ($existingMockBoardAttempt->attempt_count + 1)
                        : 1,
                ];

                if ($isNewBestForMockBoard) {
                    $mockBoardAttemptValues += [
                        'quiz_attempt_id' => $attempt->id,
                        'score' => $correctCount,
                        'total' => $totalQuestions,
                        'percentage' => $percentage,
                        'passed' => $percentage >= $passingGrade,
                    ];
                }

                MockBoardAttempt::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'mock_board_phase_id' => $mockPhase->id,
                    ],
                    $mockBoardAttemptValues
                );
            }

            return response()->json(['success' => true, 'score' => $correctCount, 'percentage' => $percentage, 'passed' => $isPassed]);
        });
    }

    /**
     * Return the authenticated user's attempt history for a module,
     * newest first, scoped to a single quiz stage. Summary fields only —
     * no questions_snapshot, so the collapsed list view stays light.
     */
    public function attemptHistory(Request $request, Module $module)
    {
        $user = Auth::user();
        $stage = $this->resolveStage($request);

        $snapshots = QuizAttemptSnapshot::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('quiz_stage', $stage)
            ->orderByDesc('attempt_number')
            ->get([
                'id',
                'attempt_number',
                'score',
                'total',
                'percentage',
                'passed',
                'completed_at',
            ]);

        return response()->json([
            'success' => true,
            'attempts' => $snapshots,
        ]);
    }

    /**
     * Return the full per-question breakdown for a single attempt
     * snapshot, lazy-loaded when the student expands a row.
     */
    public function attemptSnapshotDetail(QuizAttemptSnapshot $snapshot)
    {
        if ($snapshot->user_id !== Auth::id()) {
            abort(403);
        }

        $questions = $snapshot->questions_snapshot ?: [];

        // If questions_snapshot is empty or missing options, backfill from QuizQuestion & QuizAnswer
        if (empty($questions) || (isset($questions[0]) && empty($questions[0]['options']))) {
            $dbQuestions = QuizQuestion::where('module_id', $snapshot->module_id)
                ->when($snapshot->quiz_stage, fn ($q) => $q->where('quiz_stage', $snapshot->quiz_stage))
                ->orderBy('order')
                ->get();

            $attempt = QuizAttempt::where('user_id', $snapshot->user_id)
                ->where('module_id', $snapshot->module_id)
                ->when($snapshot->quiz_stage, fn ($q) => $q->where('quiz_stage', $snapshot->quiz_stage))
                ->first();

            $answers = $attempt
                ? QuizAnswer::where('attempt_id', $attempt->id)->get()->keyBy('question_id')
                : collect();

            $questionsMap = collect($questions)->keyBy('question_id');

            $questions = $dbQuestions->map(function (QuizQuestion $q) use ($answers, $questionsMap) {
                $ans = $answers->get($q->id);
                $snapQ = $questionsMap->get($q->id);

                $selected = $snapQ['selected_option'] ?? $ans?->selected_option;
                $correct = $snapQ['correct_option'] ?? $q->correct_option;
                $isCorrect = isset($snapQ['is_correct'])
                    ? (bool) $snapQ['is_correct']
                    : ($ans ? (bool) $ans->is_correct : ($selected && strtoupper(trim((string) $selected)) === strtoupper(trim((string) $correct))));

                return [
                    'question_id' => $q->id,
                    'question_text' => $q->question_text,
                    'options' => $q->options ?: [],
                    'correct_option' => $correct,
                    'selected_option' => $selected,
                    'is_correct' => $isCorrect,
                ];
            })->values()->toArray();
        }

        return response()->json([
            'success' => true,
            'attempt_number' => $snapshot->attempt_number,
            'score' => $snapshot->score,
            'total' => $snapshot->total,
            'percentage' => $snapshot->percentage,
            'passed' => $snapshot->passed,
            'completed_at' => $snapshot->completed_at,
            'questions' => $questions,
        ]);
    }

    /**
     * Return comprehensive item analysis & question breakdown for the student's
     * latest completed attempt for a given module (and stage).
     */
    public function latestAttemptAnalysis(Request $request, Module $module)
    {
        $user = Auth::user();
        $stage = $this->resolveStage($request);

        $attempt = QuizAttempt::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->when($stage, fn ($q) => $q->where('quiz_stage', $stage))
            ->where('status', 'completed')
            ->latest('updated_at')
            ->first();

        $snapshot = QuizAttemptSnapshot::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->when($stage, fn ($q) => $q->where('quiz_stage', $stage))
            ->latest('completed_at')
            ->first();

        if (! $attempt && ! $snapshot) {
            return response()->json([
                'success' => false,
                'message' => 'No completed attempt found for item analysis.',
            ], 404);
        }

        $questions = QuizQuestion::where('module_id', $module->id)
            ->when($stage, fn ($q) => $q->where('quiz_stage', $stage))
            ->orderBy('order')
            ->get();

        $answers = $attempt
            ? QuizAnswer::where('attempt_id', $attempt->id)->get()->keyBy('question_id')
            : collect();

        $snapshotMap = collect($snapshot?->questions_snapshot ?? [])->keyBy('question_id');

        $analysis = $questions->map(function (QuizQuestion $q, int $index) use ($answers, $snapshotMap) {
            $ans = $answers->get($q->id);
            $snap = $snapshotMap->get($q->id);

            $selected = $ans?->selected_option ?? ($snap['selected_option'] ?? null);
            $correct = $q->correct_option ?: ($snap['correct_option'] ?? null);

            $isCorrect = $ans
                ? (bool) $ans->is_correct
                : (isset($snap['is_correct'])
                    ? (bool) $snap['is_correct']
                    : ($selected && strtoupper(trim((string) $selected)) === strtoupper(trim((string) $correct))));

            return [
                'index' => $index + 1,
                'question_id' => $q->id,
                'question_text' => $q->question_text,
                'options' => $q->options ?: [],
                'correct_option' => $correct,
                'selected_option' => $selected,
                'is_correct' => $isCorrect,
                'is_unanswered' => empty($selected),
                'points' => $q->points ?? 1,
                'explanation' => $q->explanation,
                'domain' => $q->domain,
                'difficulty' => $q->difficulty,
            ];
        })->values();

        if ($analysis->isEmpty() && $snapshot && ! empty($snapshot->questions_snapshot)) {
            $analysis = collect($snapshot->questions_snapshot)->map(function ($sq, int $index) {
                return [
                    'index' => $index + 1,
                    'question_id' => $sq['question_id'] ?? null,
                    'question_text' => $sq['question_text'] ?? 'Question '.($index + 1),
                    'options' => $sq['options'] ?? [],
                    'correct_option' => $sq['correct_option'] ?? null,
                    'selected_option' => $sq['selected_option'] ?? null,
                    'is_correct' => (bool) ($sq['is_correct'] ?? false),
                    'is_unanswered' => empty($sq['selected_option']),
                    'points' => 1,
                    'explanation' => $sq['explanation'] ?? null,
                    'domain' => $sq['domain'] ?? null,
                    'difficulty' => $sq['difficulty'] ?? null,
                ];
            })->values();
        }

        $total = $analysis->count();
        $correct = $analysis->where('is_correct', true)->count();
        $unanswered = $analysis->where('is_unanswered', true)->count();
        $incorrect = $total - $correct;
        $score = $attempt?->score ?? ($snapshot?->score ?? $correct);
        $percentage = $total > 0 ? (float) round(($correct / $total) * 100) : 0;
        $passed = $attempt?->passed ?? ($snapshot?->passed ?? ($percentage >= ($module->passing_grade ?? 50)));

        return response()->json([
            'success' => true,
            'attempt_number' => $snapshot?->attempt_number ?? ($attempt?->attempt_count ?? 1),
            'score' => $score,
            'total' => $total,
            'percentage' => $percentage,
            'passed' => $passed,
            'summary' => [
                'total' => $total,
                'correct' => $correct,
                'incorrect' => $incorrect,
                'unanswered' => $unanswered,
                'percentage' => $percentage,
            ],
            'questions' => $analysis,
        ]);
    }

    /**
     * Teacher: itakda ang base na bilang ng attempts na pinapayagan sa
     * lahat ng estudyante para sa module/assessment na ito. Isa pa lang ito
     * per module — parehong sinusunod ng pre-test at post-test kung
     * parehong is_formal_assessment ang lecture module.
     */
    public function updateMaxAttempts(Request $request, Module $module)
    {
        $class = $module->class;
        $isOwnerOrAdmin = $class
            ? ($class->created_by === Auth::id() || Auth::user()->role === 'admin')
            : (Auth::user()->role === 'admin' || Auth::user()->role === 'teacher');

        if (! $isOwnerOrAdmin) {
            abort(403);
        }

        $validated = $request->validate([
            'max_attempts' => 'required|integer|min:1|max:20',
        ]);

        $module->update(['max_attempts' => $validated['max_attempts']]);

        return response()->json([
            'success' => true,
            'message' => 'Naka-set na ngayon sa '.$validated['max_attempts'].' ang base na attempts para sa assessment na ito.',
            'max_attempts' => $module->max_attempts,
        ]);
    }

    /**
     * Teacher: magbigay ng karagdagang attempts sa isang partikular na
     * estudyante, bukod sa base max_attempts ng module.
     */
    public function grantExtraAttempt(Request $request, Module $module, User $student)
    {
        $class = $module->class;
        $isOwnerOrAdmin = $class
            ? ($class->created_by === Auth::id() || Auth::user()->role === 'admin')
            : (Auth::user()->role === 'admin' || Auth::user()->role === 'teacher');

        if (! $isOwnerOrAdmin) {
            abort(403);
        }

        $validated = $request->validate([
            'extra_attempts' => 'required|integer|min:1|max:10',
            'reason' => 'nullable|string|max:255',
        ]);

        $grant = AssessmentAttemptGrant::updateOrCreate(
            ['module_id' => $module->id, 'user_id' => $student->id],
            [
                'extra_attempts' => $validated['extra_attempts'],
                'granted_by' => Auth::id(),
                'reason' => $validated['reason'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $student->name.' ay bibigyan ng '.$validated['extra_attempts'].' karagdagang attempt(s).',
            'total_allowed' => ($module->max_attempts ?? 1) + $grant->extra_attempts,
        ]);
    }

    public function resetMyAttempt(Request $request, Module $module)
    {
        return response()->json([
            'success' => false,
            'message' => 'Self-reset is not allowed. Please contact your instructor to request an additional attempt.',
        ], 403);
    }

    public function updateModuleSettings(Request $request, Module $module)
    {
        $class = $module->class;
        $isOwnerOrAdmin = $class
            ? ($class->created_by === Auth::id() || in_array(Auth::user()->role, ['admin', 'superadmin'], true))
            : (in_array(Auth::user()->role, ['admin', 'superadmin', 'teacher'], true));

        if (! $isOwnerOrAdmin) {
            abort(403);
        }

        $validated = $request->validate([
            'max_attempts' => 'nullable|integer|min:1|max:20',
            'time_limit' => 'nullable|integer|min:0',
            'passing_grade' => 'nullable|integer|min:1|max:100',
            'available_at' => 'nullable|date',
            'due_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = [];
        if ($request->has('max_attempts')) {
            $updateData['max_attempts'] = $validated['max_attempts'];
        }
        if ($request->has('time_limit')) {
            $updateData['time_limit'] = $validated['time_limit'];
        }
        if ($request->has('passing_grade')) {
            $updateData['passing_grade'] = $validated['passing_grade'];
        }
        if ($request->has('available_at')) {
            $updateData['available_at'] = $validated['available_at'] ?: null;
        }
        if ($request->has('due_date')) {
            $updateData['due_date'] = $validated['due_date'] ?: null;
        }
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }

        $module->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Assessment settings updated successfully.',
            'module' => $module->fresh(),
        ]);
    }

    public function show(Module $module)
    {
        return view('quiz.show', [
            'module' => $module,
            'questions' => $module->quizQuestions,
            'durationSeconds' => ($module->time_limit ?? 0) * 60,
        ]);
    }

    public function createQuizDraft(Request $request, ClassModel $class)
    {
        $rawTime = $request->input('time_limit', null);
        $minutes = is_numeric($rawTime) ? (int) $rawTime : 0;
        $rawAttempts = $request->input('max_attempts', 1);
        $maxAttempts = is_numeric($rawAttempts) ? max(1, (int) $rawAttempts) : 1;
        $rawPassingGrade = $request->input('passing_grade', null);
        $passingGrade = is_numeric($rawPassingGrade) ? (int) $rawPassingGrade : 50;

        $stage = $request->input('quiz_stage');

        $module = Module::create([
            'class_id' => $class->id,
            'title' => $request->title,
            'description' => $request->description,
            'time_limit' => $minutes,
            'passing_grade' => $passingGrade,
            'max_attempts' => $maxAttempts,
            'due_date' => $request->input('due_date') ?: null,
            'available_at' => $request->input('available_at') ?: null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : false,
            'is_quiz' => true,
            'is_formal_assessment' => (bool) $request->is_formal_assessment,
            'quiz_stage' => $stage,
            'assessment_purpose' => $request->input('assessment_purpose', $stage),
            'visibility' => $request->input('visibility', 'all'),
            'created_by' => Auth::id(),
        ]);

        if (in_array($request->input('visibility'), ['selected', 'except']) && $request->has('visible_user_ids')) {
            $enrolledIds = $class->students()->pluck('users.id')->toArray();
            $visibleUserIds = array_values(array_intersect((array) $request->input('visible_user_ids', []), $enrolledIds));
            $module->visibleTo()->sync($visibleUserIds);
        }

        return redirect()->route('quiz.create', $module);
    }
}
