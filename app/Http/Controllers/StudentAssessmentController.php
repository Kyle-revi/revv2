<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\Auth;

class StudentAssessmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function resolveLayout(): string
    {
        $user = Auth::user();
        $track = $user->role === 'student' ? ($user->program ?? 'accountancy') : $user->role;

        return match ($track) {
            'psych' => 'layouts.appPsych',
            'educ' => 'layouts.appEduc',
            'teacher' => 'layouts.appTeach',
            'admin', 'superadmin' => 'layouts.appAdmin',
            default => 'layouts.appAcc',
        };
    }

    public function index()
    {
        $user = Auth::user();
        $layout = $this->resolveLayout();

        $classIds = $user->classes()->pluck('classes.id');
        $classes = $user->classes()->orderBy('name')->get(['classes.id', 'classes.name']);

        $assessments = Module::query()
            ->whereIn('class_id', $classIds)
            ->where('is_formal_assessment', true)
            ->where(function ($query) use ($user) {
                $query->where('visibility', 'all')
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('visibility', 'selected')
                            ->whereHas('visibleTo', fn ($q) => $q->where('users.id', $user->id));
                    })
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('visibility', 'except')
                            ->whereDoesntHave('visibleTo', fn ($q) => $q->where('users.id', $user->id));
                    });
            })
            ->with([
                'class',
                'attemptGrants' => fn ($query) => $query->where('user_id', $user->id),
                'attempts' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->latest('updated_at'),
            ])
            ->withCount('quizQuestions')
            ->orderBy('title')
            ->get()
            ->each(function (Module $module) use ($user) {
                $module->student_attempt = $module->attempts->first();
                $module->attempts_used = (int) ($module->attempts->max('attempt_count') ?? ($module->student_attempt?->attempt_count ?? 0));
                $module->attempts_allowed = $module->allowedAttemptsFor($user->id);
                $module->is_upcoming = $module->isUpcoming();
                $module->is_open = $module->isOpen();
                $module->is_closed = $module->isClosed();
                $module->status_label = $module->statusLabel();
            });

        return view('pages.student.assessment', compact('assessments', 'classes', 'layout'));
    }

    public function take(Module $module)
    {
        $user = Auth::user();

        if (! $module->is_formal_assessment) {
            abort(404);
        }

        if (! $module->class->users()->where('user_id', $user->id)->exists()) {
            abort(403, 'You are not enrolled in this class.');
        }

        if ($module->isUpcoming()) {
            abort(403, 'This assessment is not active yet. It will open on '.($module->available_at?->format('M d, Y g:i A') ?? 'the scheduled date').'.');
        }

        if (! ($module->is_active ?? true)) {
            abort(403, 'This assessment is currently inactive.');
        }

        if ($module->isOverdue()) {
            abort(403, 'This assessment is past its due date and can no longer be taken.');
        }

        $questions = $module->quizQuestions()->orderBy('order')->get();

        $module->load([
            'attemptGrants' => fn ($query) => $query->where('user_id', $user->id),
        ]);
        $attempt = QuizAttempt::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->orderByDesc('percentage')
            ->first();
        $attempts_used = (int) (QuizAttempt::where('user_id', $user->id)->where('module_id', $module->id)->max('attempt_count') ?? ($attempt?->attempt_count ?? 0));
        $attempts_allowed = $module->allowedAttemptsFor($user->id);
        $is_resuming = $attempt?->status === 'in_progress';
        $can_start_attempt = $is_resuming || $attempts_used < $attempts_allowed;

        // Kung nire-resume, ipasa ang deadline (huling activity + 1 minuto, in ms)
        // para makapag-countdown ang frontend at ma-warn agad ang estudyante.
        $resume_deadline_ms = ($is_resuming && $attempt)
            ? $attempt->updated_at->timestamp * 1000 + 60000
            : null;

        return view('pages.student.assessment-take', compact(
            'module',
            'questions',
            'attempts_used',
            'attempts_allowed',
            'is_resuming',
            'can_start_attempt',
            'resume_deadline_ms',
        ));
    }

    public function results(Module $module)
    {
        $user = Auth::user();

        if (! $module->is_formal_assessment) {
            abort(404);
        }

        if (! $module->class->users()->where('user_id', $user->id)->exists()) {
            abort(403, 'You are not enrolled in this class.');
        }

        $attempt = QuizAttempt::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('status', 'completed')
            ->first();

        if (! $attempt) {
            return redirect()->route('assessment.take', $module)
                ->with('info', 'No completed attempt found yet for this assessment.');
        }

        return view('pages.student.assessment-take', [
            'module' => $module,
            'questions' => collect(),
            'attempts_used' => $attempt->attempt_count ?? 0,
            'attempts_allowed' => $module->allowedAttemptsFor($user->id),
            'is_resuming' => false,
            'can_start_attempt' => false,
            'viewResultsOnly' => true,
            'resultScore' => $attempt->score,
            'resultTotal' => $attempt->total,
            'resultPercentage' => $attempt->percentage,
        ]);
    }
}
