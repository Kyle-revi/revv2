<?php

namespace App\Http\Controllers;

use App\Models\MockBoard;
use App\Models\MockBoardAttempt;
use App\Models\MockBoardPhase;
use App\Models\Module;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptSnapshot;
use App\Services\MockBoardStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentMockBoardController extends Controller
{
    /**
     * Maps short program codes to their full names.
     * Used to normalize mismatched program values (e.g. "psych" vs "psychology").
     */
    private array $programMap = [
        'psych' => 'psychology',
        'psychology' => 'psychology',
        'educ' => 'education',
        'education' => 'education',
        'accountancy' => 'accountancy',
        'acc' => 'accountancy',
        'bsa' => 'accountancy',
        'nursing' => 'nursing',
        'bsn' => 'nursing',
        'crim' => 'criminology',
        'criminology' => 'criminology',
        'bscrim' => 'criminology',
    ];

    public function __construct(
        private MockBoardStatisticsService $statisticsService
    ) {}

    /**
     * Normalize a program string: lowercase, trimmed, mapped to full name.
     */
    private function normalizeProgram(?string $program): string
    {
        $raw = strtolower(trim($program ?? ''));

        return $this->programMap[$raw] ?? $raw;
    }

    /**
     * Check if a mock board's program matches the user's program,
     * regardless of case or short-code vs full-name differences.
     */
    private function programsMatch(MockBoard $mockBoard, $user): bool
    {
        return $this->normalizeProgram($mockBoard->program) === $this->normalizeProgram($user->program);
    }

    /**
     * Teacher: list mock boards na sarili niyang ginawa, naka-scope sa program niya.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (in_array($user->role, ['teacher', 'admin'], true)) {
            $normalizedProgram = $this->normalizeProgram($user->program);
            $rawProgram = strtolower(trim($user->program ?? ''));

            $mockBoards = MockBoard::where('teacher_id', $user->id)
                ->when($normalizedProgram !== '', function ($q) use ($normalizedProgram, $rawProgram) {
                    $q->where(function ($sq) use ($normalizedProgram, $rawProgram) {
                        $sq->whereRaw('LOWER(program) = ?', [$normalizedProgram])
                            ->orWhereRaw('LOWER(program) = ?', [$rawProgram]);
                    });
                })
                ->with(['phases.module.quizQuestions'])
                ->withCount('attempts')
                ->orderBy('created_at', 'desc')
                ->get();

            return view('pages.teacher.mock-boards.batch-dashboard', [
                'mockBoards' => $mockBoards,
                'selectedProgram' => $normalizedProgram,
            ]);
        }

        // Student: makita ang mga APPROVED mock boards na naka-match sa program niya
        $normalizedStudentProgram = $this->normalizeProgram($user->program);

        $availableBoards = MockBoard::where('status', 'approved')
            ->with([
                'phases' => function ($q) {
                    $q->orderByRaw("CASE WHEN phase_type = 'pre_test' THEN 1 ELSE 2 END ASC, sequence_number ASC");
                },
                'phases.module',
                'attempts' => function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                },
            ])
            ->get()
            ->filter(fn ($mb) => $this->normalizeProgram($mb->program) === $normalizedStudentProgram)
            ->values();

        $programLayoutMap = [
            'psych' => 'layouts.appPsych',
            'accountancy' => 'layouts.appAcc',
            'educ' => 'layouts.app',
        ];
        $layout = $programLayoutMap[$user->program] ?? 'layouts.app';

        return view('pages.student.mock-boards.index', [
            'availableBoards' => $availableBoards,
            'layout' => $layout,
        ]);

    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'review_period_start' => 'required|date',
            'review_period_end' => 'required|date|after_or_equal:review_period_start',
            'passing_percentage' => 'required|integer|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1|max:20',
            'selected_phase' => 'required|in:pre_test,pre_boards',
            'pre_test_title' => 'nullable|string|max:255',
            'pre_boards_title' => 'nullable|string|max:255',
            'time_limit' => 'nullable|integer|min:0',
        ]);

        // Ang program ay palaging kinukuha mula sa account ng teacher, hindi user input —
        // iniiwasan ang pag-upload ng mock board sa maling program.
        $program = $this->normalizeProgram($user->program);

        if ($program === '') {
            return redirect()->back()->with('error', 'Your account has no assigned program. Contact an admin.');
        }

        return DB::transaction(function () use ($validated, $program, $user) {

            $mockBoard = MockBoard::create([
                'teacher_id' => $user->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'program' => $program,
                'review_period_start' => $validated['review_period_start'],
                'review_period_end' => $validated['review_period_end'],
                'passing_percentage' => $validated['passing_percentage'],
                'status' => 'pending',
            ]);

            $selectedPhase = $validated['selected_phase'];
            $phaseTitle = $selectedPhase === 'pre_test'
                ? ($validated['pre_test_title'] ?? $validated['title'].' - Pre-Test')
                : ($validated['pre_boards_title'] ?? $validated['title'].' - Pre-Boards');
            $phaseModule = Module::create([
                'title' => $phaseTitle,
                'is_quiz' => true,
                'is_formal_assessment' => true,
                'is_mock_board' => true,
                'class_id' => null,
                'passing_percentage' => $validated['passing_percentage'],
                'time_limit' => $validated['time_limit'] ?? 0,
                'max_attempts' => ! empty($validated['max_attempts']) ? (int) $validated['max_attempts'] : 1,
                'created_by' => $user->id,
            ]);

            MockBoardPhase::create([
                'mock_board_id' => $mockBoard->id,
                'phase_type' => $selectedPhase,
                'title' => $phaseTitle,
                'module_id' => $phaseModule->id,
            ]);

            $examName = $selectedPhase === 'pre_test' ? 'Pre-Test exam' : 'Pre-Boards exam';

            return redirect()
                ->route('quiz.create', $phaseModule)
                ->with('success', "Mock Board created and submitted for admin approval. Start by building the {$examName} now.");
        });
    }

    /**
     * Teacher: i-update ang sariling mock board — babalik sa pending kung may nabagong laman.
     */
    public function update(Request $request, MockBoard $mockBoard)
    {
        $user = auth()->user();

        if ($mockBoard->teacher_id !== $user->id) {
            abort(403, 'You do not have permission to update this Mock Board.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'review_period_start' => 'sometimes|date',
            'review_period_end' => 'sometimes|date|after_or_equal:review_period_start',
            'passing_percentage' => 'sometimes|integer|min:0|max:100',
            'max_attempts' => 'sometimes|integer|min:1|max:20',
        ]);

        $mockBoard->update($validated);

        // Anumang pagbabago sa laman ay kailangang muling i-review ng admin
        $mockBoard->resetToPending();

        if (isset($validated['passing_percentage']) || isset($validated['max_attempts'])) {
            foreach ($mockBoard->phases as $phase) {
                if ($phase->module) {
                    $updateFields = [];
                    if (isset($validated['passing_percentage'])) {
                        $updateFields['passing_percentage'] = $validated['passing_percentage'];
                    }
                    if (isset($validated['max_attempts'])) {
                        $updateFields['max_attempts'] = (int) $validated['max_attempts'];
                    }
                    $phase->module->update($updateFields);
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Mock Board updated and resubmitted for approval.',
                'mock_board' => $mockBoard->fresh(),
            ]);
        }

        return redirect()->back()->with('success', 'Mock Board updated and resubmitted for admin approval.');
    }

    /**
     * Teacher: burahin ang sariling mock board lamang.
     */
    public function destroy(Request $request, MockBoard $mockBoard)
    {
        $user = auth()->user();

        if ($mockBoard->teacher_id !== $user->id) {
            abort(403, 'You do not have permission to delete this Mock Board.');
        }

        foreach ($mockBoard->phases as $phase) {
            if ($phase->module) {
                $phase->module->delete();
            }
            $phase->delete();
        }

        $mockBoard->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Mock Board and all associated data deleted successfully',
            ]);
        }

        return redirect()->back()->with('success', 'Mock Board deleted successfully.');
    }

    /**
     * Teacher: magdagdag ng missing phase (Pre-Test o Pre-Boards) sa sariling mock board.
     */
    /**
     * Teacher: magdagdag ng bagong phase sa sariling mock board.
     *
     * pre_test ay nananatiling isa lang (business rule), pero pre_boards
     * (post-test) ay maaari nang magkaroon ng maramihan — bawat bagong
     * post-test phase ay awtomatikong nakukuha ang susunod na
     * sequence_number at default na label ("Post-Test 2", "Post-Test 3", ...).
     */
    public function addPhase(Request $request, MockBoard $mockBoard)
    {
        $user = auth()->user();

        if ($mockBoard->teacher_id !== $user->id) {
            abort(403, 'You do not have permission to modify this Mock Board.');
        }

        $validated = $request->validate([
            'phase_type' => 'required|in:pre_test,pre_boards',
            'title' => 'nullable|string|max:255',
            'label' => 'nullable|string|max:255',
            'max_attempts' => 'nullable|integer|min:1|max:20',
        ]);

        $phaseType = $validated['phase_type'];

        $existingOfType = $mockBoard->phases()->where('phase_type', $phaseType)->orderBy('sequence_number')->get();

        // pre_test stays capped at exactly one phase; pre_boards (post-test)
        // may have as many as the teacher wants to add.
        if ($phaseType === 'pre_test' && $existingOfType->isNotEmpty()) {
            return redirect()->back()->with('error', 'This phase already exists for this Mock Board.');
        }

        $nextSequence = ($existingOfType->max('sequence_number') ?? 0) + 1;

        $baseLabel = $phaseType === 'pre_test' ? 'Pre-Test' : 'Pre-Boards';
        $defaultLabel = $nextSequence > 1 ? "Post-Test {$nextSequence}" : $baseLabel;
        $phaseLabel = $validated['label'] ?? $defaultLabel;
        $phaseTitle = $validated['title'] ?? ($mockBoard->title.' - '.$phaseLabel);

        $inheritedAttempts = $mockBoard->phases->first()?->module?->max_attempts ?? 1;
        $maxAttempts = ! empty($validated['max_attempts']) ? (int) $validated['max_attempts'] : $inheritedAttempts;

        $phaseModule = Module::create([
            'title' => $phaseTitle,
            'is_quiz' => true,
            'is_formal_assessment' => true,
            'is_mock_board' => true,
            'class_id' => null,
            'passing_percentage' => $mockBoard->passing_percentage,
            'time_limit' => 0,
            'max_attempts' => $maxAttempts,
            'created_by' => $user->id,
        ]);

        MockBoardPhase::create([
            'mock_board_id' => $mockBoard->id,
            'phase_type' => $phaseType,
            'sequence_number' => $nextSequence,
            'label' => $nextSequence > 1 ? $phaseLabel : null,
            'title' => $phaseTitle,
            'module_id' => $phaseModule->id,
        ]);

        // Content changed — needs re-approval
        $mockBoard->resetToPending();

        return redirect()
            ->route('quiz.create', $phaseModule)
            ->with('success', "{$phaseLabel} phase created successfully. Start building the exam now.");
    }

    /**
     * Teacher: i-update ang phase details (title, question_ids, is_same_questions).
     */
    public function updatePhases(Request $request, MockBoard $mockBoard)
    {
        $user = auth()->user();

        if ($mockBoard->teacher_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'phases' => 'required|array',
            'phases.pre_test.title' => 'sometimes|string|max:255',
            'phases.pre_test.question_ids' => 'nullable|array',
            'phases.pre_test.is_same_questions' => 'sometimes|boolean',
            'phases.pre_boards.title' => 'sometimes|string|max:255',
            'phases.pre_boards.question_ids' => 'nullable|array',
            'phases.pre_boards.is_same_questions' => 'sometimes|boolean',
        ]);

        foreach ($validated['phases'] as $phaseType => $data) {
            /** @var MockBoardPhase|null $phase */
            $phase = $mockBoard->phases()->where('phase_type', $phaseType)->first();
            if ($phase) {
                $updateData = [];
                if (isset($data['title'])) {
                    $updateData['title'] = $data['title'];
                    if ($phase->module) {
                        $phase->module->update(['title' => $data['title']]);
                    }
                }
                if (isset($data['question_ids'])) {
                    $updateData['question_ids'] = $data['question_ids'];
                }
                if (isset($data['is_same_questions'])) {
                    $updateData['is_same_questions'] = $data['is_same_questions'];
                }
                $phase->update($updateData);
            }
        }

        // Content changed — needs re-approval
        $mockBoard->resetToPending();

        return response()->json([
            'message' => 'Phases updated successfully and resubmitted for approval',
            'phases' => $mockBoard->fresh()->phases,
        ]);
    }

    /**
     * Show mock board details via JSON (for modals or dynamic views).
     */
    public function show(MockBoard $mockBoard)
    {
        $user = auth()->user();

        if (! $this->programsMatch($mockBoard, $user)) {
            abort(403, 'This Mock Board is not assigned to your program.');
        }

        if (! $mockBoard->isApproved()) {
            abort(403, 'This Mock Board is not yet available.');
        }

        $mockBoard->load(['phases' => function ($q) {
            $q->orderByRaw("CASE WHEN phase_type = 'pre_test' THEN 1 ELSE 2 END ASC, sequence_number ASC");
        }, 'statistics']);

        $attempts = $mockBoard->attempts()
            ->where('user_id', $user->id)
            ->with('quizAttempt')
            ->get()
            ->keyBy('mock_board_phase_id');

        $canTake = $this->canTakePhase($mockBoard, $user);

        // Full per-phase breakdown — supports any number of post-test phases.
        $phases = $mockBoard->phases->map(function (MockBoardPhase $phase) use ($attempts, $canTake) {
            return [
                'id' => $phase->id,
                'phase_type' => $phase->phase_type,
                'sequence_number' => $phase->sequence_number,
                'label' => $phase->phase_label,
                'attempt' => $attempts->get($phase->id),
                'can_take' => $canTake,
            ];
        })->values();

        // Backward-compatible top-level keys (first phase of each type) for
        // any existing frontend code still reading pre_test/pre_boards directly.
        $preTestPhase = $mockBoard->phases->firstWhere('phase_type', 'pre_test');
        $preBoardsPhase = $mockBoard->phases->where('phase_type', 'pre_boards')->sortBy('sequence_number')->first();

        $preTest = $preTestPhase ? $attempts->get($preTestPhase->id) : null;
        $preBoards = $preBoardsPhase ? $attempts->get($preBoardsPhase->id) : null;

        return response()->json([
            'mock_board' => $mockBoard,
            'phases' => $phases,
            'pre_test' => $preTest,
            'pre_boards' => $preBoards,
            'can_take_pre_test' => $canTake,
            'can_take_pre_boards' => $canTake,
            'improvement' => ($preTest && $preBoards) ? $preBoards->percentage - $preTest->percentage : null,
        ]);
    }

    /**
     * Take a specific mock board phase exam. Identified by phase ID (not
     * phase_type) so a board can have multiple post-test phases without
     * ambiguity about which one is being taken.
     */
    public function take(MockBoard $mockBoard, MockBoardPhase $mockBoardPhase)
    {
        $user = auth()->user();

        if ($mockBoardPhase->mock_board_id !== $mockBoard->id) {
            abort(404);
        }

        if (! $this->programsMatch($mockBoard, $user)) {
            abort(403, 'This Mock Board is not assigned to your program.');
        }

        if (! $mockBoard->isApproved()) {
            abort(403, 'This Mock Board is not yet available.');
        }

        if (! $this->canTakePhase($mockBoard, $user)) {
            abort(403, 'Phase not available.');
        }

        $mockBoardPhase->loadMissing('module.quizQuestions');

        $module = $mockBoardPhase->module;
        $questions = $module->quizQuestions;

        $attemptsUsed = QuizAttempt::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('mock_board_id', $mockBoard->id)
            ->whereNotNull('completed_at')
            ->count();

        // Use the same rule as regular assessments: default 1 + any extra grants
        $attemptsAllowed = $module->allowedAttemptsFor($user->id);
        $canStartAttempt = $attemptsUsed < $attemptsAllowed;
        $isResuming = false;

        if (! $canStartAttempt) {
            return redirect()
                ->route('student.mock-boards.index')
                ->with('error', 'You have no attempts remaining for this exam.');
        }

        return view('pages.student.assessment-take', [
            'module' => $module,
            'questions' => $questions,
            'isMockBoard' => true,
            'mockBoard' => $mockBoard,
            'mockBoardPhase' => $mockBoardPhase,
            'phase' => $mockBoardPhase->phase_type,
            'can_start_attempt' => $canStartAttempt,
            'is_resuming' => $isResuming,
            'attempts_used' => $attemptsUsed,
            'attempts_allowed' => $attemptsAllowed,
        ]);
    }

    /**
     * Submit mock board answers.
     */
    public function submit(Request $request, MockBoard $mockBoard, MockBoardPhase $mockBoardPhase)
    {
        $user = auth()->user();

        if ($mockBoardPhase->mock_board_id !== $mockBoard->id) {
            return response()->json(['message' => 'Phase does not belong to this Mock Board'], 404);
        }

        if (! $this->programsMatch($mockBoard, $user)) {
            return response()->json(['message' => 'Unauthorized for this program'], 403);
        }

        try {
            if (! $request->has('answers')) {
                return response()->json(['message' => 'No answers received'], 422);
            }

            $mockBoardPhase->loadMissing('module.quizQuestions');

            $module = $mockBoardPhase->module;
            $questions = $module->quizQuestions->keyBy('id');

            $score = 0;
            $total = count($request->answers);
            $processedAnswers = [];

            foreach ($request->answers as $ans) {
                $question = $questions->get($ans['question_id'] ?? null);
                $isCorrect = $question ? $this->checkAnswer($question, $ans['answer'] ?? null) : false;

                if ($isCorrect) {
                    $score++;
                }

                $processedAnswers[] = [
                    'question_id' => $ans['question_id'],
                    'answer' => $ans['answer'],
                    'is_correct' => $isCorrect,
                ];
            }

            $percentage = $total > 0 ? round(($score / $total) * 100) : 0;

            return DB::transaction(function () use ($user, $module, $mockBoard, $mockBoardPhase, $score, $total, $percentage, $processedAnswers) {

                // 1. Save standard Quiz Attempt
                $quizAttempt = QuizAttempt::create([
                    'user_id' => $user->id,
                    'module_id' => $module->id,
                    'mock_board_id' => $mockBoard->id,
                    'score' => $score,
                    'total' => $total,
                    'percentage' => $percentage,
                    'passed' => $percentage >= ($module->passing_grade ?? 75),
                    'completed_at' => now(),
                ]);

                foreach ($processedAnswers as $answerData) {
                    $quizAttempt->answers()->create($answerData);
                }

                // 2. Save/Update MockBoardAttempt
                //
                // IMPORTANT: MockBoardAttempt is the cached "official" result used
                // by both the student's own dashboard and every passing-rate
                // computation (individual and program-level). Per product
                // decision, an individual's pass/fail for a phase is determined
                // by their BEST score across all attempts, not their most recent
                // one. So we only overwrite the cached score/passed fields when
                // the new submission actually beats the existing best — the
                // attempt_count still increments every time so history/analytics
                // can see how many tries the student took.
                //
                // Keyed by mock_board_phase_id (not phase_type) since a board
                // can now have multiple phases of the same phase_type (e.g.
                // several post-tests) — phase_type alone is no longer unique.
                $existing = MockBoardAttempt::where([
                    'user_id' => $user->id,
                    'mock_board_phase_id' => $mockBoardPhase->id,
                ])->first();

                $isNewBest = ! $existing || $percentage > ($existing->percentage ?? -1);

                $mockBoardAttemptValues = [
                    'mock_board_id' => $mockBoard->id,
                    'phase_type' => $mockBoardPhase->phase_type,
                    'attempt_count' => $existing ? ($existing->attempt_count + 1) : 1,
                ];

                if ($isNewBest) {
                    $mockBoardAttemptValues += [
                        'quiz_attempt_id' => $quizAttempt->id,
                        'score' => $score,
                        'total' => $total,
                        'percentage' => $percentage,
                        'passed' => $percentage >= $mockBoard->passing_percentage,
                        // Reset cached AI insights so the frontend re-fetches fresh
                        // ones for this attempt instead of showing stale/wrong text
                        // from a previous submission.
                        'ai_strong' => null,
                        'ai_weak' => null,
                        'ai_recommendation' => null,
                    ];
                }

                $mockBoardAttempt = MockBoardAttempt::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'mock_board_phase_id' => $mockBoardPhase->id,
                    ],
                    $mockBoardAttemptValues
                );

                return response()->json([
                    'success' => true,
                    'redirect' => route('student.mock-boards.index'),
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Mock Board Submit Error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get AI Insights for an attempt.
     */
    public function insights(Request $request, MockBoard $mockBoard, MockBoardPhase $mockBoardPhase)
    {
        $user = auth()->user();

        if ($mockBoardPhase->mock_board_id !== $mockBoard->id) {
            return response()->json(['message' => 'Phase does not belong to this Mock Board'], 404);
        }

        $attempt = MockBoardAttempt::where([
            'user_id' => $user->id,
            'mock_board_phase_id' => $mockBoardPhase->id,
        ])->with('quizAttempt.answers.question')->first();

        if (! $attempt || ! $attempt->quizAttempt) {
            return response()->json(['message' => 'Attempt data not found.'], 404);
        }

        $subjectPerformance = [];

        foreach ($attempt->quizAttempt->answers as $answer) {
            $question = $answer->question;
            $subject = $question->category ?? $question->subject ?? 'General Assessment';

            if (! isset($subjectPerformance[$subject])) {
                $subjectPerformance[$subject] = ['correct' => 0, 'total' => 0];
            }

            $subjectPerformance[$subject]['total']++;
            if ($answer->is_correct) {
                $subjectPerformance[$subject]['correct']++;
            }
        }

        $strongAreas = [];
        $weakAreas = [];

        foreach ($subjectPerformance as $subject => $data) {
            $accuracy = ($data['correct'] / $data['total']) * 100;

            if ($accuracy >= 75) {
                $strongAreas[] = "$subject (".round($accuracy).'% Mastery)';
            } else {
                $weakAreas[] = "$subject (".round($accuracy).'% Mastery)';
            }
        }

        if (empty($subjectPerformance)) {
            $recommendation = "No answers were recorded for this attempt, so we can't generate insights. Make sure to select an answer for each question before submitting.";
        } elseif (empty($weakAreas)) {
            $recommendation = 'Excellent performance across all tested categories! Keep up the great work to maintain your edge for the board exams.';
        } else {
            $recommendation = 'Action Required: Prioritize reviewing your low-scoring concepts, specifically targeting '.implode(', ', array_map(fn ($val) => explode(' (', $val)[0], $weakAreas)).'.';
        }

        $attempt->update([
            'ai_strong' => empty($strongAreas) ? 'None identified yet' : implode(', ', $strongAreas),
            'ai_weak' => empty($weakAreas) ? 'None identified yet' : implode(', ', $weakAreas),
            'ai_recommendation' => $recommendation,
        ]);

        return response()->json([
            'strong_areas' => $strongAreas,
            'weak_areas' => $weakAreas,
            'recommendation' => $recommendation,
        ]);
    }

    /**
     * Helper to check if a student is allowed to take a phase based on program.
     */
    private function canTakePhase(MockBoard $mockBoard, $user): bool
    {
        if (! $this->programsMatch($mockBoard, $user)) {
            return false;
        }

        // Pansamantalang tinanggal ang date restriction para ma-access agad ang mock boards
        return true;
    }

    private function checkAnswer($question, $answer): bool
    {
        $correctAnswer = $question->correct_answer;

        return is_array($correctAnswer) ? in_array($answer, $correctAnswer) : $answer == $correctAnswer;
    }

    /**
     * Ipakita ang buod ng performance (Pre-Test vs Pre-Boards) ng estudyante
     * para sa isang partikular na mock board, kasama na ang AI Insights
     * (cached mula sa MockBoardAttempt) at Attempt History (mula sa
     * QuizAttempt records), kagaya ng result screen sa assessment.take.blade.
     */
    public function results(MockBoard $mockBoard)
    {
        $user = auth()->user();

        if (! $this->programsMatch($mockBoard, $user)) {
            abort(403, 'This Mock Board is not assigned to your program.');
        }

        $mockBoard->load(['phases' => function ($q) {
            $q->orderByRaw("CASE WHEN phase_type = 'pre_test' THEN 1 ELSE 2 END ASC, sequence_number ASC");
        }, 'phases.module']);

        // Keyed by mock_board_phase_id — the correct identity now that a
        // board can have multiple phases sharing the same phase_type (e.g.
        // several post-tests). Keying by phase_type alone would silently
        // collapse those extra phases onto one row.
        $rawAttemptsByPhaseId = MockBoardAttempt::where('user_id', $user->id)
            ->where('mock_board_id', $mockBoard->id)
            ->get()
            ->keyBy('mock_board_phase_id');

        $mapAttempt = function ($attempt) {
            if (! $attempt) {
                return null;
            }

            return (object) [
                'score' => $attempt->score,
                'total_questions' => $attempt->total,
                'percentage' => $attempt->percentage,
                'passed' => $attempt->passed,
                'ai_strong' => $attempt->ai_strong,
                'ai_weak' => $attempt->ai_weak,
                'ai_recommendation' => $attempt->ai_recommendation,
            ];
        };

        // Backward-compatible shape (first phase of each type) so the
        // existing results.blade.php keeps working unchanged.
        $preTestPhase = $mockBoard->phases->firstWhere('phase_type', 'pre_test');
        $preBoardsPhase = $mockBoard->phases->where('phase_type', 'pre_boards')->sortBy('sequence_number')->first();

        $attempts = collect([
            'pre_test' => $preTestPhase ? $mapAttempt($rawAttemptsByPhaseId->get($preTestPhase->id)) : null,
            'pre_boards' => $preBoardsPhase ? $mapAttempt($rawAttemptsByPhaseId->get($preBoardsPhase->id)) : null,
        ])->filter();

        // Full per-phase breakdown — every post-test phase gets its own
        // entry instead of only the first one being visible.
        $phasesDetail = $mockBoard->phases->map(function (MockBoardPhase $phase) use ($rawAttemptsByPhaseId, $mapAttempt) {
            return [
                'id' => $phase->id,
                'phase_type' => $phase->phase_type,
                'sequence_number' => $phase->sequence_number,
                'label' => $phase->phase_label,
                'attempt' => $mapAttempt($rawAttemptsByPhaseId->get($phase->id)),
            ];
        })->values();

        // Overall post-test result: best score across all post-test phases
        // the student has attempted, per the "best score if individually"
        // product decision — not an average across attempts/phases.
        $postTestAttempts = $mockBoard->phases
            ->where('phase_type', 'pre_boards')
            ->map(fn (MockBoardPhase $phase) => $rawAttemptsByPhaseId->get($phase->id))
            ->filter();

        $bestPostTestAttempt = $postTestAttempts->sortByDesc('percentage')->first();

        $overallPostTest = $bestPostTestAttempt ? [
            'best_percentage' => $bestPostTestAttempt->percentage,
            'passed' => $bestPostTestAttempt->percentage >= $mockBoard->passing_percentage,
            'phases_attempted' => $postTestAttempts->count(),
            'phases_total' => $mockBoard->phases->where('phase_type', 'pre_boards')->count(),
        ] : null;

        // Buuin ang Attempt History (per phase) mula sa QuizAttemptSnapshot
        // records, kasama ang per-question breakdown para sa expandable view.
        // Keyed by phase_type (backward-compatible, first phase of each
        // type) AND by phase id (history_by_phase, supports every post-test).
        $history = [];
        $historyByPhaseId = [];

        $buildHistoryForPhase = function (?MockBoardPhase $phaseModel) use ($user, $mockBoard) {
            if (! $phaseModel || ! $phaseModel->module_id) {
                return [];
            }

            // QuizAttemptSnapshot is the dedicated append-only history table —
            // one row per completed attempt, unlike QuizAttempt which is a
            // single overwritten row per (user, module, mock_board_id) and
            // can never hold more than one entry's worth of history.
            $isPreTest = ($phaseModel->phase_type === 'pre_test');

            return QuizAttemptSnapshot::where('user_id', $user->id)
                ->where('module_id', $phaseModel->module_id)
                ->where('mock_board_id', $mockBoard->id)
                ->orderBy('attempt_number', 'asc')
                ->get()
                ->map(function ($snap) use ($isPreTest) {
                    return [
                        'attempt_number' => $snap->attempt_number,
                        'score' => $snap->score,
                        'total' => $snap->total,
                        'percentage' => $snap->percentage,
                        'passed' => $snap->passed,
                        'completed_at' => optional($snap->completed_at)->toIso8601String(),
                        'is_pre_test' => $isPreTest,
                        'questions' => $isPreTest ? [] : collect($snap->questions_snapshot ?? [])->map(function ($q) {
                            return [
                                'question_text' => $q['question_text'] ?? '',
                                'options' => $q['options'] ?? [],
                                'selected_option' => $q['selected_option'] ?? null,
                                'correct_option' => $q['correct_option'] ?? null,
                                'is_correct' => (bool) ($q['is_correct'] ?? false),
                            ];
                        })->values(),
                    ];
                })
                ->values();
        };

        $history['pre_test'] = $buildHistoryForPhase($preTestPhase);
        $history['pre_boards'] = $buildHistoryForPhase($preBoardsPhase);

        foreach ($mockBoard->phases as $phaseModel) {
            $historyByPhaseId[$phaseModel->id] = $buildHistoryForPhase($phaseModel);
        }

        return view('pages.student.mock-boards.results', [
            'mockBoard' => $mockBoard,
            'attempts' => $attempts,
            'history' => $history,
            'phasesDetail' => $phasesDetail,
            'historyByPhaseId' => $historyByPhaseId,
            'overallPostTest' => $overallPostTest,
        ]);
    }
}
