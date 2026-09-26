<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\HistoricalBoardExamResult;
use App\Models\MockBoard;
use App\Models\User;
use App\Services\MockBoardStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BatchAnalyticsController extends Controller
{
    public function __construct(
        private MockBoardStatisticsService $statisticsService
    ) {}

    public function dashboard(Request $request)
    {
        $user = auth()->user();

        if (! in_array($user->role, ['admin', 'superadmin', 'teacher'], true)) {
            abort(403, 'Unauthorized');
        }

        $teacherClassIds = ClassModel::where('created_by', $user->id)->pluck('id');

        $teacherStudentIds = DB::table('class_user')
            ->whereIn('class_id', $teacherClassIds)
            ->pluck('user_id');

        if (in_array($user->role, ['admin', 'superadmin'])) {
            $programs = MockBoard::whereNotNull('program')
                ->where('program', '!=', '')
                ->pluck('program')
                ->unique()
                ->values()
                ->toArray();
            if (empty($programs)) {
                $programs = ['psychology', 'education', 'accountancy'];
            }
        } else {
            $programMap = [
                'psych' => 'psychology',
                'educ' => 'education',
                'accountancy' => 'accountancy',
            ];

            $rawTeacherProgram = strtolower(trim($user->program ?? ''));
            $normalizedTeacherProgram = $programMap[$rawTeacherProgram] ?? $rawTeacherProgram;

            $programs = ! empty($normalizedTeacherProgram) ? [$normalizedTeacherProgram] : [];
        }

        $selectedProgram = $request->query('program', $programs[0] ?? '');

        if (! in_array($selectedProgram, $programs) && ! empty($programs)) {
            $selectedProgram = $programs[0];
        }

        $mockBoardsByProgram = [];
        $mockBoardModelsByProgram = [];

        if (! empty($programs)) {
            foreach ($programs as $program) {
                $variants = [$program];
                if ($program === 'psychology') {
                    $variants = ['psychology', 'psych'];
                }
                if ($program === 'education') {
                    $variants = ['education', 'educ'];
                }

                $mockBoards = MockBoard::whereIn('program', $variants)
                    ->when($user->role === 'teacher', function ($q) use ($user) {
                        $q->where('teacher_id', $user->id);
                    })
                    ->with(['statistics', 'attempts.user', 'phases.module.quizQuestions'])
                    ->withCount('attempts')
                    ->orderBy('created_at', 'desc')
                    ->get();
                $mockBoardModelsByProgram[$program] = $mockBoards;

                $mockBoardsByProgram[$program] = $mockBoards->map(function ($mockBoard) use ($program, $teacherStudentIds, $user) {
                    $stats = $mockBoard->statistics;

                    // Live computation mula sa attempts — hindi na umaasa sa
                    // MockBoardStatistic cache na hindi naman na-u-update automatically.
                    $preBoardsAttempts = $mockBoard->attempts->where('phase_type', 'pre_boards')->whereNotNull('percentage');
                    $preTestAttempts = $mockBoard->attempts->where('phase_type', 'pre_test')->whereNotNull('percentage');
                    $currentAttempts = $preBoardsAttempts->count() > 0 ? $preBoardsAttempts : $preTestAttempts;

                    $averageScore = $currentAttempts->count() > 0 ? round($currentAttempts->avg('percentage'), 2) : 0;
                    $passingRate = $currentAttempts->count() > 0
                        ? round(($currentAttempts->where('passed', true)->count() / $currentAttempts->count()) * 100, 2)
                        : 0;

                    if ($user->role === 'teacher') {
                        $totalProgramStudents = User::whereIn('id', $teacherStudentIds)
                            ->where('program', 'LIKE', "%{$program}%")
                            ->where('role', 'student')
                            ->count();
                    } else {
                        $totalProgramStudents = User::where('program', 'LIKE', "%{$program}%")
                            ->where('role', 'student')
                            ->count();
                    }

                    return [
                        'id' => $mockBoard->id,
                        'title' => $mockBoard->title,
                        'class_name' => ucfirst($program).' Program Batch',
                        'total_students' => $totalProgramStudents,
                        'pre_test_participants' => $preTestAttempts->unique('user_id')->count(),   // BAGO: live count
                        'pre_boards_participants' => $preBoardsAttempts->unique('user_id')->count(), // BAGO: live count
                        'pre_test_mean' => $stats?->pre_test_mean,
                        'pre_boards_mean' => $stats?->pre_boards_mean,
                        'improvement_percentage' => $stats?->improvement_percentage,
                        'anova_significant' => $stats?->anova_significant,
                        'review_period_end' => $mockBoard->review_period_end,
                        'passing_rate' => $passingRate,
                        'average_score' => $averageScore,
                        'is_active' => true,
                    ];
                });
            }
        }

        $teacherNavClassId = $teacherClassIds->first() ?? null;

        $currentProgramMockBoards = $mockBoardsByProgram[$selectedProgram] ?? collect();
        $mockBoards = $mockBoardModelsByProgram[$selectedProgram] ?? collect();
        $mockBoards = $mockBoardModelsByProgram[$selectedProgram] ?? collect();

        // BAGO: i-attach ang live participant counts sa bawat board model
        // para makita ito ng List section sa Blade ($board->pre_test_participants)
        foreach ($mockBoards as $board) {
            $preBoardsAttempts = $board->attempts->where('phase_type', 'pre_boards')->whereNotNull('percentage');
            $preTestAttempts = $board->attempts->where('phase_type', 'pre_test')->whereNotNull('percentage');

            $board->setAttribute('pre_test_participants', $preTestAttempts->unique('user_id')->count());
            $board->setAttribute('pre_boards_participants', $preBoardsAttempts->unique('user_id')->count());
        }
        if ($user->role === 'teacher') {
            $totalProgramStudents = ! empty($selectedProgram)
                ? User::whereIn('id', $teacherStudentIds)->where('role', 'student')->where('program', 'LIKE', "%{$selectedProgram}%")->count()
                : 0;
        } else {
            $totalProgramStudents = ! empty($selectedProgram)
                ? User::where('role', 'student')->where('program', 'LIKE', "%{$selectedProgram}%")->count()
                : 0;
        }

        $classAverageScore = count($currentProgramMockBoards) > 0 ? collect($currentProgramMockBoards)->avg('average_score') : 0;
        $highestScore = count($currentProgramMockBoards) > 0 ? collect($currentProgramMockBoards)->max('average_score') : 0;
        $completionRate = count($currentProgramMockBoards) > 0 ? collect($currentProgramMockBoards)->avg('passing_rate') : 0;

        return view('pages.teacher.mock-boards.batch-dashboard', [
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'activeProgram' => $selectedProgram,
            'mockBoards' => $mockBoards,
            'mock_boards_by_program' => $mockBoardsByProgram,
            'totalProgramStudents' => $totalProgramStudents,
            'classAverageScore' => $classAverageScore,
            'completionRate' => $completionRate,
            'highestScore' => $highestScore,
            'teacherNavClassId' => $teacherNavClassId,
            'isAdmin' => in_array($user->role, ['admin', 'superadmin']), // BAGO

        ]);
    }

    public function mockBoardsAnalysis(Request $request, string $program, MockBoard $mockBoard)
    {
        $user = auth()->user();

        if (! in_array($user->role, ['admin', 'superadmin', 'teacher'])) {
            abort(403, 'You do not have permission to view batch analytics.');
        }

        if ($user->role === 'teacher') {
            // Strictly enforce: A teacher can ONLY view analysis of their OWN created Mock Boards
            if ((int) $mockBoard->teacher_id !== (int) $user->id) {
                abort(403, 'You do not have permission to view this Mock Board\'s analysis.');
            }

            // Normalize program aliases (educ -> education, psych -> psychology)
            $programMap = [
                'psych' => 'psychology',
                'educ' => 'education',
                'accountancy' => 'accountancy',
            ];

            $rawTeacherProgram = strtolower(trim($user->program ?? ''));
            $normalizedTeacherProgram = $programMap[$rawTeacherProgram] ?? $rawTeacherProgram;
            $normalizedUrlProgram = $programMap[strtolower(trim($program))] ?? strtolower(trim($program));

            if (! empty($normalizedTeacherProgram) && $normalizedTeacherProgram !== $normalizedUrlProgram) {
                abort(403, 'Unauthorized access to this program analysis.');
            }
        }

        if (strtolower($mockBoard->program) !== strtolower($program)) {
            abort(404, 'This Mock Board does not belong to the selected program.');
        }

        $hierarchical_stats = $this->statisticsService->computeHierarchicalStats($mockBoard, $program);
        $student_results = $this->statisticsService->getDetailedStudentResults($mockBoard, $program);
        $anova = $this->statisticsService->calculateBatchANOVA($mockBoard, $program);
        $forecast = $this->statisticsService->computeForecastedPassRate($mockBoard, $program);

        // Gamitin ang computeHierarchicalBatchStats para dito — ito ang mayroon
        // ng tunay na batch-level totals (batch_passing_rate / total_batch_students).
        // Ang computeHierarchicalStats() sa itaas ay 'classes' array lang ang laman,
        // kaya hiwalay na tinatawag dito para sa summary card.
        $batchTotals = $this->statisticsService->computeHierarchicalBatchStats($program, $mockBoard->id);

        $summary = [
            'total_students' => $batchTotals['total_batch_students'] ?? 0,
            'pre_boards_passing_rate' => $batchTotals['batch_passing_rate'] ?? 0,
        ];

        // BAGO: Bilangin ang bumagsak na estudyante, naka-group by kanilang unang klase.
        // "Bumagsak" = hindi nag-pasa sa kahit alin sa pre_test o pre_boards (overall).
        $attemptsByUser = $mockBoard->attempts()
            ->with('user.classes')
            ->get()
            ->groupBy('user_id');

        $failedByClass = [];

        foreach ($attemptsByUser as $userId => $userAttempts) {
            $failedAnyPhase = $userAttempts->contains(fn ($a) => $a->passed === false);

            if (! $failedAnyPhase) {
                continue;
            }

            $student = $userAttempts->first()->user;
            if (! $student) {
                continue;
            }

            $firstClass = $student->classes()->orderBy('joined_at')->first();
            $className = $firstClass->name ?? 'No Class Assigned';

            if (! isset($failedByClass[$className])) {
                $failedByClass[$className] = 0;
            }
            $failedByClass[$className]++;
        }

        arsort($failedByClass); // pinaka-maraming bumagsak muna sa taas

        // BAGO: 'created_by' lang ang totoong column sa 'classes' table
        $teacherNavClassId = ClassModel::where('created_by', $user->id)->value('id');

        // Get all phases for the board (Pre-Test, Post-Test 1, Post-Test 2, etc.)
        $boardPhases = $mockBoard->phases()
            ->with('module.quizQuestions')
            ->orderByRaw("CASE WHEN phase_type = 'pre_test' THEN 1 ELSE 2 END ASC, sequence_number ASC")
            ->get();

        $boardClasses = ClassModel::where('created_by', $mockBoard->teacher_id)
            ->when($mockBoard->class_id, fn ($q) => $q->orWhere('id', $mockBoard->class_id))
            ->get();

        $selectedClassFilter = $request->query('class_filter') ? (int) $request->query('class_filter') : null;

        // Generate Item Analysis data for each phase and overall pre_test/pre_boards
        $item_analysis = [];
        foreach ($boardPhases as $phase) {
            $phaseKey = 'phase_'.$phase->id;
            $item_analysis[$phaseKey] = [
                'phase_id' => $phase->id,
                'phase_type' => $phase->phase_type,
                'phase_label' => $phase->phase_label,
                'title' => $phase->title ?? $phase->phase_label,
                'questions' => $this->statisticsService->getItemAnalysis($mockBoard, null, $phase->id, $selectedClassFilter),
            ];
        }

        // Backward compatibility keys
        $item_analysis['pre_test'] = $this->statisticsService->getItemAnalysis($mockBoard, 'pre_test', null, $selectedClassFilter);
        $item_analysis['pre_boards'] = $this->statisticsService->getItemAnalysis($mockBoard, 'pre_boards', null, $selectedClassFilter);

        // Overall post-test passing rate combined across ALL post-test phases
        // (supports mock boards with more than one post-test) — uses each
        // student's BEST score across those phases, per the confirmed
        // "best score if individually" product rule.
        $overallPostTestStats = $this->statisticsService->computeOverallPostTestPassingRate($mockBoard);

        // Comparison against a real, previous licensure exam — only present
        // when the teacher has manually linked one via
        // historical_board_exam_result_id. Delta uses the same combined
        // post-test rate above, not the single-phase Pre-Boards rate, so the
        // comparison is fair even when a board has multiple post-tests.
        $historicalComparison = null;
        $mockBoard->loadMissing('historicalBoardExamResult');

        if ($mockBoard->historicalBoardExamResult) {
            $historical = $mockBoard->historicalBoardExamResult;
            $revisoRate = $overallPostTestStats['overall_passing_rate'] ?? 0;

            $historicalComparison = [
                'exam_label' => $historical->exam_label,
                'exam_period_or_year' => $historical->exam_period_or_year,
                'historical_passing_rate' => $historical->passing_rate,
                'reviso_passing_rate' => $revisoRate,
                'delta' => round($revisoRate - $historical->passing_rate, 2),
            ];
        }

        $historicalExamOptions = HistoricalBoardExamResult::query()
            ->where('program', strtolower($mockBoard->program))
            ->orderBy('exam_period_or_year', 'desc')
            ->get();

        return view('pages.teacher.mock-boards.batch-analysis', [
            'program' => $program,
            'mockBoard' => $mockBoard,
            'boardPhases' => $boardPhases,
            'boardClasses' => $boardClasses,
            'selectedClassFilter' => $selectedClassFilter,
            'hierarchical_stats' => $hierarchical_stats,
            'student_results' => $student_results,
            'summary' => $summary,
            'anova' => $anova,
            'teacherNavClassId' => $teacherNavClassId,
            'failedByClass' => $failedByClass,
            'item_analysis' => $item_analysis,
            'forecast' => $forecast,
            'overallPostTestStats' => $overallPostTestStats,
            'historicalComparison' => $historicalComparison,
            'historicalExamOptions' => $historicalExamOptions,
            'isAdmin' => in_array($user->role, ['admin', 'superadmin'], true),
        ]);
    }

    /**
     * Individual student question-by-question item analysis for a mock board.
     */
    public function studentItemAnalysis(Request $request, string $program, MockBoard $mockBoard, User $user)
    {
        $currentUser = auth()->user();

        if (! in_array($currentUser->role, ['admin', 'superadmin', 'teacher'], true)) {
            abort(403, 'Unauthorized');
        }

        if ($currentUser->role === 'teacher') {
            if ((int) $mockBoard->teacher_id !== (int) $currentUser->id) {
                abort(403, 'Unauthorized to view this student\'s item analysis.');
            }
        }

        $phaseId = $request->query('phase_id') ? (int) $request->query('phase_id') : null;

        $studentAnalysis = $this->statisticsService->getStudentMockBoardItemAnalysis($mockBoard, $user, $phaseId);

        return response()->json([
            'success' => true,
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'idnumber' => $user->idnumber ?? '—',
                'program' => $user->program ?? $program,
            ],
            'mock_board' => [
                'id' => $mockBoard->id,
                'title' => $mockBoard->title,
                'program' => $mockBoard->program,
            ],
            'phases' => $studentAnalysis['phases'] ?? [],
            'active_phase' => $studentAnalysis['active_phase'] ?? null,
            'summary' => $studentAnalysis['summary'] ?? [],
            'questions' => $studentAnalysis['questions'] ?? [],
        ]);
    }

    public function computeANOVA(Request $request, string $program, MockBoard $mockBoard)
    {
        $user = auth()->user();

        if (! in_array($user->role, ['admin', 'superadmin', 'teacher'])) {
            abort(403, 'Unauthorized');
        }

        if ($user->role === 'teacher') {
            if ((int) $mockBoard->teacher_id !== (int) $user->id) {
                abort(403, 'Unauthorized to compute ANOVA for this Mock Board.');
            }
        }

        $programMap = [
            'psych' => ['psych', 'psychology'],
            'psychology' => ['psych', 'psychology'],
            'educ' => ['educ', 'education'],
            'education' => ['educ', 'education'],
            'accountancy' => ['accountancy'],
        ];
        $programVariants = $programMap[strtolower($program)] ?? [$program];

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

            if ($preTest?->percentage !== null) {
                $preTestScores[] = $preTest->percentage;
            }
            if ($preBoards?->percentage !== null) {
                $preBoardsScores[] = $preBoards->percentage;
            }
        }

        $anovaResults = $this->statisticsService->computeOneWayANOVA($preTestScores, $preBoardsScores);

        return response()->json([
            'success' => true,
            'anova' => $anovaResults,
            'sample_sizes' => [
                'pre_test' => count($preTestScores),
                'pre_boards' => count($preBoardsScores),
            ],
        ]);
    }
}
