<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAttemptGrant;
use App\Models\ClassModel;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\AiSettingsResolver;
use App\Services\CloudflareAI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    private function authorizeClassAccess(ClassModel $class): void
    {
        $actor = Auth::user();

        if ($actor->role === 'admin' || $actor->role === 'superadmin') {
            return;
        }

        if ($class->created_by !== $actor->id) {
            abort(403, 'You do not have permission to view this class performance.');
        }
    }

    /**
     * Build analytics payload for pre-assessment quizzes in a class.
     */
    private function buildPerformancePayload(ClassModel $class, ?int $moduleId = null): array
    {
        $settingsResolver = app(AiSettingsResolver::class);
        $isClassSummaryEnabled = $settingsResolver->isFeatureEnabled('class_summary', $class);

        $applyModuleScope = function ($q) use ($class, $moduleId) {
            $q->where('class_id', $class->id)->where('is_formal_assessment', false);
            if ($moduleId) {
                $q->where('id', $moduleId);
            }
        };

        // Class Average (pre-assessment quizzes only)
        $classAverage = QuizAttempt::whereHas('module', $applyModuleScope)->avg('percentage') ?? 0;

        // Pass / Fail
        $totalAttempts = QuizAttempt::whereHas('module', $applyModuleScope)->count();

        $passCount = QuizAttempt::whereHas('module', $applyModuleScope)
            ->where('percentage', '>=', 50)->count();

        $failCount = $totalAttempts - $passCount;

        // Per-question breakdown
        $questionStatsQuery = DB::table('quiz_answers')
            ->join('quiz_questions', 'quiz_answers.question_id', '=', 'quiz_questions.id')
            ->join('quiz_attempts', 'quiz_answers.attempt_id', '=', 'quiz_attempts.id')
            ->join('modules', 'quiz_attempts.module_id', '=', 'modules.id')
            ->where('modules.class_id', $class->id)
            ->where('modules.is_formal_assessment', false);

        if ($moduleId) {
            $questionStatsQuery->where('modules.id', $moduleId);
        }

        $questionStats = $questionStatsQuery
            ->select(
                'quiz_questions.id as question_id',
                'quiz_questions.question_text',
                'modules.id as module_id',
                'modules.title as module_title',
                DB::raw('COUNT(quiz_answers.id) as total_answers'),
                DB::raw('SUM(quiz_answers.is_correct) as correct_count'),
                DB::raw('COUNT(quiz_answers.id) - SUM(quiz_answers.is_correct) as wrong_count'),
                DB::raw('ROUND(AVG(quiz_answers.is_correct) * 100, 1) as pct_correct')
            )
            ->groupBy('quiz_questions.id', 'quiz_questions.question_text', 'modules.id', 'modules.title')
            ->orderBy('pct_correct', 'asc')
            ->get();

        // All Ranked Students
        $topStudentsQuery = User::select(
            'users.id',
            'users.idnumber',
            'users.name',
            'users.program',
            DB::raw('AVG(quiz_attempts.percentage) as average_score')
        )
            ->join('quiz_attempts', 'users.id', '=', 'quiz_attempts.user_id')
            ->join('modules', 'quiz_attempts.module_id', '=', 'modules.id')
            ->where('modules.class_id', $class->id)
            ->where('modules.is_formal_assessment', false);

        if ($moduleId) {
            $topStudentsQuery->where('modules.id', $moduleId);
        }

        $topStudents = $topStudentsQuery
            ->groupBy('users.id', 'users.idnumber', 'users.name', 'users.program')
            ->orderByDesc('average_score')
            ->get();

        $totalStudents = $class->students()->count() ?? 0;
        $remainingCount = 0;
        $aiSummary = $class->ai_summary ?? 'No AI summary yet. Click refresh to generate one.';

        return [
            'selectedModuleId' => $moduleId,
            'classAverage' => (float) $classAverage,
            'passCount' => (int) $passCount,
            'failCount' => (int) $failCount,
            'questionStats' => $questionStats,
            'topStudents' => $topStudents,
            'remainingCount' => $remainingCount,
            'aiSummary' => $aiSummary,
            'totalStudents' => $totalStudents,
            'classSummaryEnabled' => $isClassSummaryEnabled,
        ];
    }

    /**
     * Build analytics payload for formal assessments in a class.
     */
    private function buildAssessmentPayload(ClassModel $class, ?int $moduleId = null): array
    {
        $settingsResolver = app(AiSettingsResolver::class);
        $isAssessmentAnalysisEnabled = $settingsResolver->isFeatureEnabled('assessment_analysis', $class);

        $applyModuleScope = function ($q) use ($class, $moduleId) {
            $q->where('class_id', $class->id)->where('is_formal_assessment', true);
            if ($moduleId) {
                $q->where('id', $moduleId);
            }
        };

        $classAverage = QuizAttempt::whereHas('module', $applyModuleScope)->avg('percentage') ?? 0;

        $totalAttempts = QuizAttempt::whereHas('module', $applyModuleScope)->count();

        $passCount = QuizAttempt::whereHas('module', $applyModuleScope)
            ->where('percentage', '>=', 50)->count();

        $failCount = $totalAttempts - $passCount;

        $questionStatsQuery = DB::table('quiz_answers')
            ->join('quiz_questions', 'quiz_answers.question_id', '=', 'quiz_questions.id')
            ->join('quiz_attempts', 'quiz_answers.attempt_id', '=', 'quiz_attempts.id')
            ->join('modules', 'quiz_attempts.module_id', '=', 'modules.id')
            ->where('modules.class_id', $class->id)
            ->where('modules.is_formal_assessment', true);

        if ($moduleId) {
            $questionStatsQuery->where('modules.id', $moduleId);
        }

        $questionStats = $questionStatsQuery
            ->select(
                'quiz_questions.id as question_id',
                'quiz_questions.question_text',
                'modules.id as module_id',
                'modules.title as module_title',
                DB::raw('COUNT(quiz_answers.id) as total_answers'),
                DB::raw('SUM(quiz_answers.is_correct) as correct_count'),
                DB::raw('COUNT(quiz_answers.id) - SUM(quiz_answers.is_correct) as wrong_count'),
                DB::raw('ROUND(AVG(quiz_answers.is_correct) * 100, 1) as pct_correct')
            )
            ->groupBy('quiz_questions.id', 'quiz_questions.question_text', 'modules.id', 'modules.title')
            ->orderBy('pct_correct', 'asc')
            ->get();

        $topStudentsQuery = User::select(
            'users.id',
            'users.idnumber',
            'users.name',
            'users.program',
            DB::raw('AVG(quiz_attempts.percentage) as average_score')
        )
            ->join('quiz_attempts', 'users.id', '=', 'quiz_attempts.user_id')
            ->join('modules', 'quiz_attempts.module_id', '=', 'modules.id')
            ->where('modules.class_id', $class->id)
            ->where('modules.is_formal_assessment', true);

        if ($moduleId) {
            $topStudentsQuery->where('modules.id', $moduleId);
        }

        $topStudents = $topStudentsQuery
            ->groupBy('users.id', 'users.idnumber', 'users.name', 'users.program')
            ->orderByDesc('average_score')
            ->get();

        $totalStudents = $class->students()->count() ?? 0;
        $remainingCount = 0;
        $assessmentAiSummary = $class->assessment_ai_summary ?? 'No assessment AI summary yet. Click refresh to generate one.';

        return [
            'selectedModuleId' => $moduleId,
            'classAverage' => (float) $classAverage,
            'passCount' => (int) $passCount,
            'failCount' => (int) $failCount,
            'questionStats' => $questionStats,
            'topStudents' => $topStudents,
            'remainingCount' => $remainingCount,
            'aiSummary' => $assessmentAiSummary,
            'assessmentAnalysisEnabled' => $isAssessmentAnalysisEnabled,
        ];
    }

    public function studentPerformance(ClassModel $class)
    {
        $this->authorizeClassAccess($class);

        // All classes this teacher owns (for the class switcher)
        $actor = Auth::user();
        $teacherClasses = $actor->role === 'admin' || $actor->role === 'superadmin'
            ? ClassModel::orderBy('name')->get(['id', 'name'])
            : ClassModel::where('created_by', Auth::id())
                ->orderBy('name')
                ->get(['id', 'name']);

        $preAssessmentModules = Module::where('class_id', $class->id)
            ->where('is_formal_assessment', false)
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'title']);

        $formalAssessmentModules = Module::where('class_id', $class->id)
            ->where('is_formal_assessment', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'title']);

        $quizModuleId = request()->filled('quiz_module_id') && request('quiz_module_id') !== 'all'
            ? (int) request('quiz_module_id')
            : null;

        $assessModuleId = request()->filled('assess_module_id') && request('assess_module_id') !== 'all'
            ? (int) request('assess_module_id')
            : null;

        $payload = $this->buildPerformancePayload($class, $quizModuleId);
        $assessmentPayload = $this->buildAssessmentPayload($class, $assessModuleId);

        if (request()->expectsJson()) {
            return response()->json([
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                ],
                'classAverage' => $payload['classAverage'],
                'passCount' => $payload['passCount'],
                'failCount' => $payload['failCount'],
                'questionStats' => $payload['questionStats'],
                'topStudents' => $payload['topStudents'],
                'remainingCount' => $payload['remainingCount'],
                'aiSummary' => $payload['aiSummary'],
                'classSummaryEnabled' => $payload['classSummaryEnabled'],
                'selectedModuleId' => $payload['selectedModuleId'],
                'preAssessmentModules' => $preAssessmentModules,
                'assessment' => [
                    'classAverage' => $assessmentPayload['classAverage'],
                    'passCount' => $assessmentPayload['passCount'],
                    'failCount' => $assessmentPayload['failCount'],
                    'questionStats' => $assessmentPayload['questionStats'],
                    'topStudents' => $assessmentPayload['topStudents'],
                    'remainingCount' => $assessmentPayload['remainingCount'],
                    'aiSummary' => $assessmentPayload['aiSummary'],
                    'assessmentAnalysisEnabled' => $assessmentPayload['assessmentAnalysisEnabled'],
                    'selectedModuleId' => $assessmentPayload['selectedModuleId'],
                    'formalAssessmentModules' => $formalAssessmentModules,
                ],
            ]);
        }

        return view('pages.teacher.student-performance', compact(
            'class',
            'teacherClasses',
            'payload',
            'assessmentPayload',
            'preAssessmentModules',
            'formalAssessmentModules'
        ));
    }

    public function studentItemAnalysis(ClassModel $class, User $student)
    {
        $this->authorizeClassAccess($class);

        $isEnrolled = $class->students()->where('users.id', $student->id)->exists();
        if (! $isEnrolled) {
            abort(404, 'Student is not enrolled in this class.');
        }

        $isAssessment = request()->query('type') === 'assessment';
        $selectedModuleId = request()->query('module_id');

        $attemptQuery = QuizAttempt::query()
            ->where('user_id', $student->id)
            ->whereHas('module', function ($q) use ($class, $isAssessment) {
                $q->where('class_id', $class->id)
                    ->where('is_formal_assessment', $isAssessment);
            });

        if ($selectedModuleId && $selectedModuleId !== 'all') {
            $attemptQuery->where('module_id', (int) $selectedModuleId);
        }

        if ($isAssessment) {
            $latestAttempt = (clone $attemptQuery)
                ->latest('updated_at')
                ->first();
        } else {
            // For pre-assessment, prefer the latest attempt that has saved answers.
            $latestAttempt = (clone $attemptQuery)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('quiz_answers')
                        ->whereColumn('quiz_answers.attempt_id', 'quiz_attempts.id');
                })
                ->latest('updated_at')
                ->first();

            if (! $latestAttempt) {
                $latestAttempt = (clone $attemptQuery)
                    ->latest('updated_at')
                    ->first();
            }
        }

        if (! $latestAttempt) {
            $pendingAssessment = null;
            if ($isAssessment) {
                $targetModuleId = ($selectedModuleId && $selectedModuleId !== 'all')
                    ? (int) $selectedModuleId
                    : Module::where('class_id', $class->id)->where('is_formal_assessment', true)->value('id');

                if ($targetModuleId) {
                    $mod = Module::find($targetModuleId);
                    if ($mod) {
                        $grant = AssessmentAttemptGrant::where('module_id', $mod->id)
                            ->where('user_id', $student->id)
                            ->first();
                        $baseMax = $mod->max_attempts ?? 1;
                        $extra = $grant->extra_attempts ?? 0;
                        $pendingAssessment = [
                            'id' => null,
                            'score' => 0,
                            'total' => 0,
                            'percentage' => 0,
                            'created_at' => null,
                            'module_id' => $mod->id,
                            'is_formal_assessment' => true,
                            'attempts_used' => 0,
                            'base_max_attempts' => $baseMax,
                            'extra_attempts_granted' => $extra,
                            'attempts_allowed' => $baseMax + $extra,
                        ];
                    }
                }
            }

            return response()->json([
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'program' => $student->program,
                ],
                'attempt' => $pendingAssessment,
                'answers' => [],
            ]);
        }

        $answers = DB::table('quiz_answers')
            ->join('quiz_questions', 'quiz_answers.question_id', '=', 'quiz_questions.id')
            ->where('quiz_answers.attempt_id', $latestAttempt->id)
            ->select(
                'quiz_questions.id as question_id',
                'quiz_questions.question_text',
                'quiz_answers.selected_option',
                'quiz_questions.correct_option',
                'quiz_answers.is_correct'
            )
            ->orderBy('quiz_questions.id')
            ->get();

        // Attempt-limit info — para lang may saysay kapag formal assessment,
        // dahil practice modules ay walang enforced na limit (self-reset lang).
        $module = $latestAttempt->module;
        $grant = $isAssessment
            ? AssessmentAttemptGrant::where('module_id', $latestAttempt->module_id)
                ->where('user_id', $student->id)
                ->first()
            : null;

        $baseMaxAttempts = $module->max_attempts ?? 1;
        $extraAttempts = $grant->extra_attempts ?? 0;

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'program' => $student->program,
            ],
            'attempt' => [
                'id' => $latestAttempt->id,
                'score' => $latestAttempt->score,
                'total' => $latestAttempt->total,
                'percentage' => $latestAttempt->percentage,
                'created_at' => $latestAttempt->created_at,
                'module_id' => $latestAttempt->module_id,
                'is_formal_assessment' => $isAssessment,
                'attempts_used' => $latestAttempt->attempt_count ?? 1,
                'base_max_attempts' => $baseMaxAttempts,
                'extra_attempts_granted' => $extraAttempts,
                'attempts_allowed' => $baseMaxAttempts + $extraAttempts,
            ],
            'answers' => $answers,
        ]);
    }

    public function refreshAiSummary(ClassModel $class)
    {
        $this->authorizeClassAccess($class);

        $settingsResolver = app(AiSettingsResolver::class);
        if (! $settingsResolver->isFeatureEnabled('class_summary', $class)) {
            return redirect()->route('student.performance', $class)
                ->with('error', 'AI class insights are disabled for this class.');
        }

        try {
            $classAverage = QuizAttempt::whereHas('module', fn ($q) => $q->where('class_id', $class->id)->where('is_formal_assessment', false))
                ->avg('percentage') ?? 0;

            $passCount = QuizAttempt::whereHas('module', fn ($q) => $q->where('class_id', $class->id)->where('is_formal_assessment', false))
                ->where('percentage', '>=', config('quiz.pass_threshold', 50))
                ->count();

            $failCount = QuizAttempt::whereHas('module', fn ($q) => $q->where('class_id', $class->id)->where('is_formal_assessment', false))
                ->count() - $passCount;

            $questionStats = DB::table('quiz_answers')
                ->join('quiz_questions', 'quiz_answers.question_id', '=', 'quiz_questions.id')
                ->join('quiz_attempts', 'quiz_answers.attempt_id', '=', 'quiz_attempts.id')
                ->join('modules', 'quiz_attempts.module_id', '=', 'modules.id')
                ->where('modules.class_id', $class->id)
                ->where('modules.is_formal_assessment', false)
                ->select(
                    'quiz_questions.question_text',
                    DB::raw('ROUND(AVG(quiz_answers.is_correct) * 100, 1) as pct_correct')
                )
                ->groupBy('quiz_questions.id', 'quiz_questions.question_text')
                ->orderBy('pct_correct', 'asc')
                ->limit(5)
                ->get();

            $topStudents = User::select('users.name', DB::raw('AVG(quiz_attempts.percentage) as average_score'))
                ->join('quiz_attempts', 'users.id', '=', 'quiz_attempts.user_id')
                ->join('modules', 'quiz_attempts.module_id', '=', 'modules.id')
                ->where('modules.class_id', $class->id)
                ->where('modules.is_formal_assessment', false)
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('average_score')
                ->limit(10)
                ->get();

            $aiSummary = app(CloudflareAI::class)->generateSummary([
                'classAverage' => $classAverage,
                'passCount' => $passCount,
                'failCount' => $failCount,
                'weakTopics' => $questionStats->map(fn ($q) => [
                    'question' => $q->question_text,
                    'pct_correct' => $q->pct_correct,
                ])->toArray(),
                'topStudents' => $topStudents->map(fn ($s) => [
                    'name' => $s->name,
                    'average_score' => $s->average_score,
                ])->toArray(),
            ]);

            $class->ai_summary = $aiSummary;
            $class->save();

            return redirect()->route('student.performance', $class)
                ->with('success', 'AI summary refreshed successfully.');
        } catch (\Throwable $e) {
            \Log::error('AI summary refresh failed: '.$e->getMessage());

            return redirect()->route('student.performance', $class)
                ->with('error', 'Failed to refresh AI summary.');
        }
    }

    public function refreshAssessmentAiSummary(ClassModel $class)
    {
        $this->authorizeClassAccess($class);

        $settingsResolver = app(AiSettingsResolver::class);
        if (! $settingsResolver->isFeatureEnabled('assessment_analysis', $class)) {
            return redirect()->route('student.performance', $class)
                ->with('error', 'Assessment AI analysis is disabled for this class.');
        }

        try {
            $classAverage = QuizAttempt::whereHas('module', function ($q) use ($class) {
                $q->where('class_id', $class->id)
                    ->where('is_formal_assessment', true);
            })->avg('percentage') ?? 0;

            $passCount = QuizAttempt::whereHas('module', function ($q) use ($class) {
                $q->where('class_id', $class->id)
                    ->where('is_formal_assessment', true);
            })
                ->where('percentage', '>=', config('quiz.pass_threshold', 50))
                ->count();

            $failCount = QuizAttempt::whereHas('module', function ($q) use ($class) {
                $q->where('class_id', $class->id)
                    ->where('is_formal_assessment', true);
            })->count() - $passCount;

            $questionStats = DB::table('quiz_answers')
                ->join('quiz_questions', 'quiz_answers.question_id', '=', 'quiz_questions.id')
                ->join('quiz_attempts', 'quiz_answers.attempt_id', '=', 'quiz_attempts.id')
                ->join('modules', 'quiz_attempts.module_id', '=', 'modules.id')
                ->where('modules.class_id', $class->id)
                ->where('modules.is_formal_assessment', true)
                ->select(
                    'quiz_questions.question_text',
                    DB::raw('ROUND(AVG(quiz_answers.is_correct) * 100, 1) as pct_correct')
                )
                ->groupBy('quiz_questions.id', 'quiz_questions.question_text')
                ->orderBy('pct_correct', 'asc')
                ->limit(5)
                ->get();

            $topStudents = User::select('users.name', DB::raw('AVG(quiz_attempts.percentage) as average_score'))
                ->join('quiz_attempts', 'users.id', '=', 'quiz_attempts.user_id')
                ->join('modules', 'quiz_attempts.module_id', '=', 'modules.id')
                ->where('modules.class_id', $class->id)
                ->where('modules.is_formal_assessment', true)
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('average_score')
                ->limit(10)
                ->get();

            $aiSummary = app(CloudflareAI::class)->generateSummary([
                'scope' => 'formal assessments',
                'classAverage' => $classAverage,
                'passCount' => $passCount,
                'failCount' => $failCount,
                'weakTopics' => $questionStats->map(fn ($q) => [
                    'question' => $q->question_text,
                    'pct_correct' => $q->pct_correct,
                ])->toArray(),
                'topStudents' => $topStudents->map(fn ($s) => [
                    'name' => $s->name,
                    'average_score' => $s->average_score,
                ])->toArray(),
            ]);

            $class->assessment_ai_summary = $aiSummary;
            $class->save();

            return redirect()->route('student.performance', $class)
                ->with('success', 'Assessment AI summary refreshed successfully.');
        } catch (\Throwable $e) {
            \Log::error('Assessment AI summary refresh failed: '.$e->getMessage());

            return redirect()->route('student.performance', $class)
                ->with('error', 'Failed to refresh assessment AI summary.');
        }
    }

    public function studentAssessmentAnalysis(ClassModel $class, User $student)
    {
        $this->authorizeClassAccess($class);

        $isEnrolled = $class->students()->where('users.id', $student->id)->exists();
        if (! $isEnrolled) {
            abort(404, 'Student is not enrolled in this class.');
        }

        $attempts = QuizAttempt::with('module')
            ->where('user_id', $student->id)
            ->whereHas('module', fn ($q) => $q->where('class_id', $class->id)->where('is_formal_assessment', true))
            ->latest('created_at')
            ->get();

        $totalAttempts = $attempts->count();
        $avgScore = $totalAttempts > 0 ? round($attempts->avg('percentage'), 1) : 0;
        $bestScore = $totalAttempts > 0 ? round($attempts->max('percentage'), 1) : 0;
        $passedCount = $attempts->where('passed', true)->count();

        $questionPerformance = DB::table('quiz_answers')
            ->join('quiz_questions', 'quiz_answers.question_id', '=', 'quiz_questions.id')
            ->join('quiz_attempts', 'quiz_answers.attempt_id', '=', 'quiz_attempts.id')
            ->join('modules', 'quiz_attempts.module_id', '=', 'modules.id')
            ->where('quiz_attempts.user_id', $student->id)
            ->where('modules.class_id', $class->id)
            ->where('modules.is_formal_assessment', true)
            ->select(
                'quiz_questions.id as question_id',
                'quiz_questions.question_text',
                DB::raw('COUNT(quiz_answers.id) as attempt_count'),
                DB::raw('SUM(quiz_answers.is_correct) as correct_count'),
                DB::raw('ROUND(AVG(quiz_answers.is_correct) * 100, 1) as pct_correct')
            )
            ->groupBy('quiz_questions.id', 'quiz_questions.question_text')
            ->orderBy('pct_correct', 'asc')
            ->get();

        $enrolledStudent = $class->students()->where('users.id', $student->id)->first();
        $aiAnalysis = $enrolledStudent?->pivot?->assessment_ai_analysis;
        $isAssessmentAnalysisEnabled = app(AiSettingsResolver::class)->isFeatureEnabled('assessment_analysis', $class);

        return view('pages.teacher.student-assessment-analysis', compact(
            'class',
            'student',
            'attempts',
            'totalAttempts',
            'avgScore',
            'bestScore',
            'passedCount',
            'questionPerformance',
            'aiAnalysis',
            'isAssessmentAnalysisEnabled'
        ));
    }

    public function generateAssessmentAiAnalysis(ClassModel $class, User $student)
    {
        $this->authorizeClassAccess($class);

        $settingsResolver = app(AiSettingsResolver::class);
        if (! $settingsResolver->isFeatureEnabled('assessment_analysis', $class)) {
            return redirect()->route('student.assessment.analysis', [$class, $student])
                ->with('error', 'Assessment AI analysis is disabled for this class.');
        }

        $isEnrolled = $class->students()->where('users.id', $student->id)->exists();
        if (! $isEnrolled) {
            abort(404, 'Student is not enrolled in this class.');
        }

        try {
            $attempts = QuizAttempt::with('module')
                ->where('user_id', $student->id)
                ->whereHas('module', fn ($q) => $q->where('class_id', $class->id)->where('is_formal_assessment', true))
                ->latest('created_at')
                ->get();

            $questionPerformance = DB::table('quiz_answers')
                ->join('quiz_questions', 'quiz_answers.question_id', '=', 'quiz_questions.id')
                ->join('quiz_attempts', 'quiz_answers.attempt_id', '=', 'quiz_attempts.id')
                ->join('modules', 'quiz_attempts.module_id', '=', 'modules.id')
                ->where('quiz_attempts.user_id', $student->id)
                ->where('modules.class_id', $class->id)
                ->where('modules.is_formal_assessment', true)
                ->select(
                    'quiz_questions.question_text',
                    DB::raw('ROUND(AVG(quiz_answers.is_correct) * 100, 1) as pct_correct')
                )
                ->groupBy('quiz_questions.id', 'quiz_questions.question_text')
                ->orderBy('pct_correct', 'asc')
                ->get();

            $classAverage = QuizAttempt::whereHas('module', function ($q) use ($class) {
                $q->where('class_id', $class->id)
                    ->where('is_formal_assessment', true);
            })->avg('percentage') ?? 0;

            $aiAnalysis = app(CloudflareAI::class)->generateStudentAssessmentAnalysis([
                'student' => $student->name,
                'class' => $class->name,
                'averageScore' => round($attempts->avg('percentage'), 1),
                'classAverage' => round($classAverage, 1),
                'bestScore' => round($attempts->max('percentage'), 1),
                'passedCount' => $attempts->where('passed', true)->count(),
                'totalAttempts' => $attempts->count(),
                'weakTopics' => $questionPerformance->map(fn ($q) => [
                    'question' => $q->question_text,
                    'pct_correct' => $q->pct_correct,
                ])->toArray(),
            ]);

            $class->students()->updateExistingPivot($student->id, [
                'assessment_ai_analysis' => $aiAnalysis,
            ]);

            return redirect()->route('student.assessment.analysis', [$class, $student]);
        } catch (\Throwable $e) {
            \Log::error('Assessment AI analysis failed: '.$e->getMessage());

            return redirect()->route('student.assessment.analysis', [$class, $student])
                ->with('error', 'Failed to generate AI analysis. Please try again.');
        }
    }

    public function classProgressTracker(ClassModel $class)
    {
        $this->authorizeClassAccess($class);

        $actor = Auth::user();
        $teacherClasses = $actor->role === 'admin' || $actor->role === 'superadmin'
            ? ClassModel::orderBy('name')->get(['id', 'name'])
            : ClassModel::where('created_by', Auth::id())
                ->orderBy('name')
                ->get(['id', 'name']);

        $modules = Module::where('class_id', $class->id)
            ->orderBy('order')
            ->get(['id', 'title', 'order', 'is_formal_assessment']);

        $students = $class->students()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.program']);

        $studentIds = $students->pluck('id');
        $moduleIds = $modules->pluck('id');

        $progressRecords = ModuleProgress::whereIn('user_id', $studentIds)
            ->whereIn('module_id', $moduleIds)
            ->get(['user_id', 'module_id', 'progress', 'completed']);

        // Also get quiz attempts for assessment modules
        $quizAttempts = QuizAttempt::whereIn('user_id', $studentIds)
            ->whereIn('module_id', $moduleIds)
            ->where('total', '>', 0) // Only count completed attempts
            ->get(['user_id', 'module_id', 'percentage']);

        /** @var array<int, array<int, array{pct: int, completed: bool}>> $progressMap */
        $progressMap = [];

        // First populate from ModuleProgress
        foreach ($progressRecords as $record) {
            $progressMap[$record->module_id][$record->user_id] = [
                'pct' => (int) $record->progress,
                'completed' => (bool) $record->completed,
            ];
        }

        // Then supplement with QuizAttempt data for assessments
        foreach ($quizAttempts as $attempt) {
            // If no ModuleProgress record exists, use quiz data
            if (! isset($progressMap[$attempt->module_id][$attempt->user_id])) {
                $progressMap[$attempt->module_id][$attempt->user_id] = [
                    'pct' => (int) $attempt->percentage,
                    'completed' => true,
                ];
            }
        }

        $studentCount = $students->count();

        /** @var array<int, array{completed_count: int, total: int, avg_progress: float}> $moduleStats */
        $moduleStats = [];

        foreach ($modules as $module) {
            $completedCount = 0;
            $totalProgress = 0;

            foreach ($students as $student) {
                $prog = $progressMap[$module->id][$student->id] ?? ['pct' => 0, 'completed' => false];

                if ($prog['completed']) {
                    $completedCount++;
                }

                $totalProgress += $prog['pct'];
            }

            $moduleStats[$module->id] = [
                'completed_count' => $completedCount,
                'total' => $studentCount,
                'avg_progress' => $studentCount > 0 ? round($totalProgress / $studentCount, 1) : 0.0,
            ];
        }

        return view('pages.teacher.class-progress-tracker', compact(
            'class',
            'teacherClasses',
            'modules',
            'students',
            'progressMap',
            'moduleStats'
        ));
    }

    public function submitQuiz(Request $request, $moduleId)
    {
        $score = $request->input('score');
        $total = $request->input('total');
        $percentage = $request->input('percentage');

        QuizAttempt::create([
            'module_id' => $moduleId,
            'user_id' => auth()->id(),
            'score' => $score,
            'total' => $total,
            'percentage' => $percentage,
        ]);

        return response()->json(['success' => true]);
    }
}
