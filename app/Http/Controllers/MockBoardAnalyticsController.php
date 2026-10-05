<?php

namespace App\Http\Controllers;

use App\Models\ClassModel as Classes;
use App\Models\MockBoard;
use App\Models\MockBoardAttempt;
use App\Services\MockBoardStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MockBoardAnalyticsController extends Controller
{
    public function __construct(
        private MockBoardStatisticsService $statisticsService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = in_array($user->role, ['superadmin', 'admin']);

        // 1. Fetch filter options — sourced from mock_boards.program directly,
        // since Mock Boards are program-scoped (not class-scoped) now.
        if ($isAdmin) {
            $programsList = MockBoard::whereNotNull('program')->distinct()->pluck('program');
        } else {
            $programsList = MockBoard::where('teacher_id', $user->id)
                ->whereNotNull('program')
                ->distinct()
                ->pluck('program');
        }

        $classList = Classes::select('id', 'name', 'program')->get();

        // 2. Active Filter States
        $viewType = $request->input('view_type', $isAdmin ? 'batch' : 'program');
        $selectedProgram = $request->input('program', $programsList->first() ?? 'education');
        $selectedClassId = $request->input('class_id', '');

        // 3. Base Query setup for Attempts — program-scoped, no class join needed
        $query = MockBoardAttempt::query()
            ->join('users', 'users.id', '=', 'mock_board_attempts.user_id')
            ->join('mock_boards', 'mock_boards.id', '=', 'mock_board_attempts.mock_board_id');

        // Scope: teacher restriction, program filters
        if (! $isAdmin) {
            $query->where('mock_boards.teacher_id', $user->id);
            if (! empty($selectedProgram) && $selectedProgram !== 'All') {
                $query->where('mock_boards.program', $selectedProgram);
            }
        } else {
            if ($viewType === 'program' && $selectedProgram !== 'All') {
                $query->where('mock_boards.program', $selectedProgram);
            } elseif ($viewType === 'class' && ! empty($selectedClassId)) {
                $query->where('mock_boards.program', $selectedClassId);
            }
            // 'batch' view type = no filter, show everything
        }

        // 4. Extract Attempts Data — program-based fields, no class fields
        $attempts = $query->select(
            'users.id as user_id',
            'users.name as student_name',
            'users.program as student_program',
            'mock_boards.program as board_program',
            'mock_boards.passing_percentage as board_passing_percentage',
            'mock_board_attempts.phase_type',
            'mock_board_attempts.percentage',
            'mock_board_attempts.passed'
        )->get();

        // 5. Build Student Results
        $studentResults = $attempts->groupBy('user_id')->map(function ($userAttempts) {
            $first = $userAttempts->first();
            $pretest = $userAttempts->where('phase_type', 'pre_test')->first();
            $boardExam = $userAttempts->where('phase_type', 'pre_boards')->first();

            $preScore = $pretest ? (float) $pretest->percentage : null;
            $boardScore = $boardExam ? (float) $boardExam->percentage : null;

            // BAGO: "current best result" — Pre-Boards kung meron, kung wala, Pre-Test.
            // Kaparehong logic ito ng Status column sa blade, para consistent
            // ang Passing Rate / Class Average sa Status na nakikita ng user.
            $currentScore = $boardScore !== null ? $boardScore : $preScore;
            $currentPassed = $boardScore !== null
                ? ($boardExam ? (bool) $boardExam->passed : false)
                : ($pretest ? (bool) $pretest->passed : false);

            $improvement = 0;
            if ($preScore !== null && $boardScore !== null) {
                $improvement = round($boardScore - $preScore, 2);
            }

            $likelihood = 'not_started';
            if ($currentScore !== null) {
                $threshold = (int) ($first->board_passing_percentage ?? 75);
                $tierData = MockBoardStatisticsService::calculateReadinessTier((float) $currentScore, $threshold);
                $likelihood = $tierData['tier'];
            }

            return [
                'name' => $first->student_name,
                'program' => ucfirst($first->board_program ?? $first->student_program ?? 'N/A'),
                'pre_test_score' => $preScore,
                'pre_test_passed' => $pretest ? (bool) $pretest->passed : false,
                'pre_boards_score' => $boardScore,
                'pre_boards_passed' => $boardExam ? (bool) $boardExam->passed : false,
                'current_score' => $currentScore,   // BAGO
                'current_passed' => $currentPassed, // BAGO
                'improvement' => $improvement,
                'board_likelihood' => $likelihood,
            ];
        })->values();

        // 6. Metrics Summary
        $totalStudents = $studentResults->count();
        $boardPassedCount = $studentResults->where('current_passed', true)->count(); // BAGO: current_passed, hindi pre_boards_passed
        $preBoardsPassingRate = $totalStudents > 0 ? round(($boardPassedCount / $totalStudents) * 100, 2) : 0;

        $summary = [
            'total_students' => $totalStudents,
            'pre_boards_passing_rate' => $preBoardsPassingRate,
        ];

        // 7. Program Breakdown (dating "class" breakdown, ngayon per program)
        $programsGrouped = $studentResults->groupBy('program')->map(function ($students, $programName) {
            $pTotal = $students->count();
            $pPassed = $students->where('current_passed', true)->count(); // BAGO

            $pPassingRate = $pTotal > 0 ? round(($pPassed / $pTotal) * 100, 2) : 0;

            $validScores = $students->pluck('current_score')->filter(fn ($v) => $v !== null); // BAGO
            $pAvg = $validScores->count() > 0 ? round($validScores->avg(), 2) : 0;

            return [
                'class_name' => $programName, // key pinanatili para hindi na baguhin ang blade view
                'student_count' => $pTotal,
                'passing_rate' => $pPassingRate,
                'average_score' => $pAvg,
            ];
        })->values();

        $hierarchical_stats = ['classes' => $programsGrouped];

        $mockBoardQuery = MockBoard::query();

        if (! $isAdmin) {
            $mockBoardQuery->where('teacher_id', $user->id);
        }

        // Apply case-insensitive program matching regardless of view type
        if (! empty($selectedProgram) && $selectedProgram !== 'All') {
            $mockBoardQuery->whereRaw('LOWER(program) = ?', [strtolower(trim($selectedProgram))]);
        }

        $mockBoard = $mockBoardQuery->first();

        $itemAnalysis = ['pre_test' => [], 'pre_boards' => []];

        if ($mockBoard) {
            $itemAnalysis = [
                'pre_test' => $this->statisticsService->getItemAnalysis($mockBoard, 'pre_test'),
                'pre_boards' => $this->statisticsService->getItemAnalysis($mockBoard, 'pre_boards'),
            ];
        }

        return view('analytics.mock_board', [
            'isAdmin' => $isAdmin,
            'program' => $selectedProgram,
            'viewType' => $viewType,
            'programsList' => $programsList,
            'classList' => $classList,
            'selectedProgram' => $selectedProgram,
            'selectedClassId' => $selectedClassId,
            'summary' => $summary,
            'hierarchical_stats' => $hierarchical_stats,
            'student_results' => $studentResults,
            'mockBoard' => $mockBoard,
            'item_analysis' => $itemAnalysis,
        ]);
    }
}
