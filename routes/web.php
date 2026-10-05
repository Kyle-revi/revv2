<?php

use App\Http\Controllers\AdminApprovalController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BatchAnalyticsController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClassManagerController;
use App\Http\Controllers\HistoricalBoardExamController;
use App\Http\Controllers\LectureController;
use App\Http\Controllers\MockBoardApprovalController;
use App\Http\Controllers\ModuleSubpartController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StudentAssessmentController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentMockBoardController;
use App\Http\Controllers\StudentProgressController;
use App\Http\Controllers\SubpartLessonController;
use App\Http\Controllers\TeacherDashboardController;
use App\Http\Controllers\TestAiController;
use App\Http\Controllers\TestBankController;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\ClassModel;
use App\Models\Module;
use App\Models\Signup;
use App\Models\User;
use App\Services\GmailService;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

$resolveStudentDashboardPath = function (?User $user): string {
    $track = $user?->program ?: $user?->role;

    return match ($track) {
        'psych' => '/psych-dashboard',
        'educ' => '/educ-dashboard',
        default => '/accountancy-dashboard',
    };
};

$resolveUserDashboardPath = function (?User $user) use ($resolveStudentDashboardPath): ?string {
    if ($user === null) {
        return null;
    }

    return match ($user->role) {
        'student', 'psych', 'educ', 'accountancy' => $resolveStudentDashboardPath($user),
        'teacher' => '/teacher-dashboard',
        'admin', 'superadmin' => '/admin-dashboard',
        default => null,
    };
};
Route::get('/signup', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return view('signup');
})->name('signup')->middleware('guest');
// ──────────────────────────────────────────────────
// Public routes (no authentication required)
// ──────────────────────────────────────────────────

Route::get('/health', function () {
    $dbStatus = 'disconnected';
    $dbError = null;
    try {
        DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }

    return response()->json([
        'status' => 'online',
        'database' => $dbStatus,
        'database_error' => $dbError,
        'db_host' => config('database.connections.mysql.host'),
        'db_port' => config('database.connections.mysql.port'),
        'db_database' => config('database.connections.mysql.database'),
        'db_username' => config('database.connections.mysql.username'),
        'app_key_set' => ! empty(config('app.key')),
        'app_env' => config('app.env'),
        'app_debug' => config('app.debug'),
        'session_driver' => config('session.driver'),
    ]);
})->withoutMiddleware([
    StartSession::class,
    VerifyCsrfToken::class,
]);

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return view('login');
})->name('home');

Route::get('/login', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return view('login');
})->name('login')->middleware('guest');

Route::get('/pending-approval', function () {
    return view('pages.admin.pending-approval');
})->name('login.pending');

Route::get('/account-rejected', function () {
    return view('pages.admin.account-rejected');
})->name('login.rejected');

Route::post('/login', function (Request $request) use ($resolveUserDashboardPath) {
    $request->validate([
        'idnumber' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    $loginInput = trim($request->input('idnumber'));
    $password = $request->input('password');

    // Find user by idnumber (case-insensitive) or email
    $user = User::where(function ($q) use ($loginInput) {
        $q->where('idnumber', $loginInput)
            ->orWhere('email', $loginInput)
            ->orWhereRaw('LOWER(idnumber) = ?', [strtolower($loginInput)])
            ->orWhereRaw('LOWER(email) = ?', [strtolower($loginInput)]);
    })->first();

    if ($user && Hash::check($password, $user->password)) {
        Auth::login($user, $request->boolean('remember'));

        // ── Status checks ──
        if ($user->status === 'pending') {
            $email = $user->email;
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login.pending')->with('account_email', $email);
        }

        if ($user->status === 'rejected') {
            $email = $user->email;
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login.rejected')->with('account_email', $email);
        }

        // ── Session regeneration ──
        $request->session()->regenerate();

        // ── Role-based redirect ──
        $redirect = $resolveUserDashboardPath($user);

        if ($redirect === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['idnumber' => 'Your account has an unrecognized role. Please contact support.']);
        }

        return redirect($redirect);
    }

    return back()
        ->withInput($request->only('idnumber'))
        ->withErrors(['idnumber' => 'Invalid ID number or password.']);
})->middleware(['guest', 'throttle:10,1'])->name('login.post');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/')->with('status', 'You have been successfully logged out.');
})->name('logout')->middleware('auth');

// ── Password Reset ──

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->middleware('guest')->name('password.request');

Route::post('/forgot-password', function (Request $request) {
    $request->validate([
        'email' => ['required', 'email', 'exists:users,email'],
    ]);

    $user = User::where('email', $request->email)->firstOrFail();

    $plainPassword = Str::password(
        length: 14,
        letters: true,
        numbers: true,
        symbols: true,
        spaces: false
    );

    $user->forceFill([
        'password' => Hash::make($plainPassword),
    ])->save();

    if (Schema::hasColumn('users', 'remember_token')) {
        $user->setRememberToken(Str::random(60));
        $user->save();
    }

    $emailBody = "
        <h2 style=\"color: #2d3748;\">Reviso Account Password Reset</h2>
        <p>Hello,</p>
        <p>A password reset was requested for your account <strong>{$user->email}</strong>.</p>
        <div style=\"background: #f7fafc; padding: 20px; border-radius: 8px; text-align: center; font-size: 22px; font-family: monospace; letter-spacing: 2px; color: #e53e3e; margin: 24px 0; border: 1px solid #e2e8f0;\">
            <strong>{$plainPassword}</strong>
        </div>
        <p><strong>Please note:</strong></p>
        <ul style=\"color: #4a5568;\">
            <li>Change this password immediately after you log in.</li>
            <li>Do <strong>not</strong> share this email or password with anyone.</li>
            <li>If you did <strong>not</strong> request this reset, contact support as soon as possible.</li>
        </ul>
        <p>Best regards,<br>The Reviso Team</p>
    ";

    try {
        $gmailService = new GmailService;
        $gmailService->send(
            $user->email,
            'Reviso – Your New Temporary Password',
            $emailBody
        );
    } catch (Exception $e) {
        Log::error('Failed to send password reset email', [
            'email' => $user->email,
            'error' => $e->getMessage(),
        ]);

        return back()
            ->withInput()
            ->withErrors(['email' => 'We could not send the new password right now. Please try again later or contact support.']);
    }

    return redirect()->route('login')
        ->with('status', 'A new temporary password has been sent to your email address. Please check your inbox and spam/junk folder. Change the password immediately after logging in.');
})->middleware('guest')->name('password.email');

Route::get('/reset-password/{token}', function (string $token) {
    return view('auth.reset-password', ['token' => $token]);
})->middleware('guest')->name('password.reset');

Route::post('/reset-password', function (Request $request) {
    $request->validate([
        'token' => ['required'],
        'email' => ['required', 'email'],
        'password' => ['required', 'min:8', 'confirmed'],
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        }
    );

    return $status === PasswordBroker::PASSWORD_RESET
        ? redirect()->route('login')->with('status', 'Your password has been reset successfully.')
        : back()->withErrors(['email' => [__($status)]]);
})->middleware('guest')->name('password.update');

// ── Signup & Verification ──

Route::post('/signup', function (Request $request) use ($resolveUserDashboardPath) {
    $validated = $request->validate([
        'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
        'idnumber' => ['required', 'string', 'max:50', 'unique:users,idnumber'],
        'name' => ['required', 'string', 'max:150'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'role' => ['required', 'in:student,teacher'],
        'program' => ['nullable', 'string', 'in:accountancy,educ,psych,teacher'],
    ]);

    $role = $validated['role'];
    $program = null;
    if ($role === 'student') {
        $program = $validated['program'] ?? 'accountancy';
    } elseif ($role === 'teacher') {
        $program = 'teacher';
    }

    $email = $validated['email'] ?? ($validated['idnumber'].'@reviso.local');

    // Direct User Creation (Bypasses Gmail verification link for fast testing)
    $user = User::create([
        'name' => $validated['name'],
        'email' => $email,
        'idnumber' => $validated['idnumber'],
        'password' => Hash::make($validated['password']),
        'role' => $role,
        'program' => $program,
        'program_locked' => ! empty($program),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    // Immediate Auto-Login
    Auth::login($user);
    $request->session()->regenerate();

    // Redirect straight to dashboard based on role
    $redirect = $resolveUserDashboardPath($user) ?? route('dashboard');

    return redirect($redirect)->with('status', 'Welcome to Reviso! Your account has been created and activated.');
})->name('signup.post');

Route::post('/email/resend', function (Request $request) {
    $request->validate(['email' => ['required', 'email']]);

    $signup = Signup::where('email', $request->email)->whereNull('verified_at')->first();

    if (! $signup) {
        return back()->withErrors(['email' => 'No pending verification found for this email.']);
    }

    $token = Str::random(60);
    $signup->update(['verification_token' => $token]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHours(24),
        ['token' => $token]
    );

    try {
        $gmailService = app(GmailService::class);
        $gmailService->send(
            $signup->email,
            'Reviso Account Verification - Resent Link',
            '<h1>Reviso Verification Link Resent</h1>'.
            '<p>Hello,</p>'.
            '<p>We resent your verification link as requested.</p>'.
            '<p>Click the button below to verify your email address:</p>'.
            "<a href='{$verificationUrl}' style='background:#5e72e4;color:white;padding:12px 24px;text-decoration:none;border-radius:4px;'>Verify Email Address</a>".
            '<p>If you did not request this, no further action is required.</p>'.
            '<p>Thanks,<br>Reviso Team</p>'
        );
    } catch (Throwable $e) {
        Log::error('Resend verification email failed: '.$e->getMessage());

        return back()->withErrors(['email' => 'Could not send verification email right now. Please try again later.']);
    }

    return back()->with('status', 'Verification link resent! Check your inbox and spam folder.');
})->name('verification.resend')->middleware('guest');

Route::get('/email/verify/{token}', function (Request $request, $token) {
    if (! $request->hasValidSignature()) {
        abort(403, 'Invalid or expired verification link.');
    }

    $signup = Signup::where('verification_token', $token)->first();

    if (! $signup) {
        return redirect()->route('login')->with('error', 'Invalid verification token.');
    }

    User::create([
        'name' => $signup->name,
        'email' => $signup->email,
        'idnumber' => $signup->idnumber,
        'password' => $signup->password,
        'role' => $signup->role ?? 'student',
        'program' => $signup->program ?? null,
        'program_locked' => ! empty($signup->program),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $signup->update([
        'verification_token' => null,
        'verified_at' => now(),
    ]);

    return redirect()->route('login')
        ->with('status', 'Email verified and account activated successfully! You can now log in.');
})->name('verification.verify');

// ── Gmail OAuth ──

Route::get('/authorize-gmail', function () {
    $client = new Google_Client;
    $client->setAuthConfig(storage_path('app/google/credentials.json'));
    $client->addScope(Google_Service_Gmail::GMAIL_SEND);
    $client->setRedirectUri('http://127.0.0.1:8000/oauth2callback');
    $client->setAccessType('offline');
    $client->setPrompt('consent');

    return redirect($client->createAuthUrl());
})->name('gmail.authorize');

Route::get('/oauth2callback', function (Request $request) {
    $client = new Google_Client;
    $client->setAuthConfig(storage_path('app/google/credentials.json'));
    $client->setRedirectUri('http://127.0.0.1:8000/oauth2callback');

    $accessToken = $client->fetchAccessTokenWithAuthCode($request->code);

    if (isset($accessToken['error'])) {
        return 'Error: '.$accessToken['error_description'];
    }

    file_put_contents(storage_path('app/google/tokens.json'), json_encode($accessToken));

    return 'Success! Tokens saved. You can now send emails from this Gmail account.';
})->name('oauth2callback');

// ── Public class invite join ──

// Temporary signed link (expires in 7 days)
Route::get('/classes/{class}/join', function (ClassModel $class) {
    if (! request()->hasValidSignature()) {
        abort(403, 'Invalid or expired invite link.');
    }

    if (! Auth::check()) {
        return redirect()->route('login')->with('warning', 'Please log in to join the class.');
    }

    $user = Auth::user();
    $class->users()->syncWithoutDetaching($user->id);

    return redirect()->route('dashboard')
        ->with('success', 'You have joined '.$class->name.'!');
})->name('class.join')->middleware('signed');

// Generated join link (24 hour expiration) - Show confirmation
Route::get('/join/{class}', function (ClassModel $class) {
    if (! request()->hasValidSignature()) {
        abort(403, 'Invalid or expired invite link (valid for 24 hours).');
    }

    if (! Auth::check()) {
        return redirect()->route('login')->with('warning', 'Please log in to join the class.');
    }

    $user = Auth::user();

    // Only students can join via this link
    if ($user->role !== 'student') {
        return redirect()->route('dashboard')
            ->with('error', 'Only students can join classes via invite link.');
    }

    // Check if already joined
    $isAlreadyJoined = $class->users()->where('user_id', $user->id)->exists();

    return view('pages.student.join-confirm', [
        'class' => $class,
        'isAlreadyJoined' => $isAlreadyJoined,
    ]);
})->name('class.join.permanent')->middleware('signed');

// Process join confirmation
Route::post('/join/{class}/confirm', function (ClassModel $class) {
    if (! request()->hasValidSignature()) {
        abort(403, 'Invalid or expired invite link.');
    }

    if (! Auth::check()) {
        return redirect()->route('login');
    }

    $user = Auth::user();

    if ($user->role !== 'student') {
        return redirect()->route('dashboard')->with('error', 'Only students can join classes.');
    }

    $class->users()->syncWithoutDetaching($user->id);

    return redirect()->route('student.classes')
        ->with('success', 'You have successfully joined '.$class->name.'!');
})->name('class.join.confirm')->middleware('signed');

// ──────────────────────────────────────────────────
// Protected routes (auth required)
// ──────────────────────────────────────────────────

Route::middleware('auth')->group(function () use ($resolveUserDashboardPath) {

    // ── Dashboards ──
    Route::get('/dashboard', function () use ($resolveUserDashboardPath) {
        $redirectPath = $resolveUserDashboardPath(Auth::user());

        if ($redirectPath === null) {
            abort(403, 'Unauthorized role');
        }

        return redirect($redirectPath);
    })->name('dashboard');
});

Route::get('/psych-dashboard', [StudentDashboardController::class, 'index'])->name('psychDashboard')->middleware('auth');
Route::get('/educ-dashboard', [StudentDashboardController::class, 'index'])->name('educDashboard')->middleware('auth');
Route::get('/teacher-dashboard', [TeacherDashboardController::class, 'index'])->name('teacherDashboard')->middleware('auth');
Route::get('/admin-dashboard', [AdminDashboardController::class, 'index'])->name('adminDashboard')->middleware('auth');
Route::get('/accountancy-dashboard', [StudentDashboardController::class, 'index'])->name('accountancyDashboard')->middleware('auth');

$resolveRoleView = function (string $page) {
    $user = Auth::user();
    $role = $user?->role;
    $track = $role === 'student' ? ($user?->program ?? 'accountancy') : $role;

    $view = "pages.{$track}.{$page}";
    if (View::exists($view)) {
        return view($view);
    }

    $legacyView = "pages.{$role}.{$page}";
    if ($role !== null && View::exists($legacyView)) {
        return view($legacyView);
    }

    $fallback = "pages.accountancy.{$page}";
    if (View::exists($fallback)) {
        return view($fallback);
    }

    abort(404, "View not found for role: {$role}");
};

Route::get('/assessment', [StudentAssessmentController::class, 'index'])->name('assessment');
Route::get('/assessment/{module}', [StudentAssessmentController::class, 'take'])->name('assessment.take');
Route::get('/assessment/{module}/results', [StudentAssessmentController::class, 'results'])->name('assessment.results');
Route::get('/progress', [StudentProgressController::class, 'index'])->name('progress');
Route::get('/tables', fn () => $resolveRoleView('tables'))->name('tables');
Route::get('/register', fn () => $resolveRoleView('register'))->name('register');
Route::get('/assessmentexams', fn () => $resolveRoleView('assessmentexams'))->name('assessmentexams');

// ── Profile ──
Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
Route::post('/profile/email', [ProfileController::class, 'updateEmail'])->name('profile.email.update');
Route::post('/profile/email/new', [ProfileController::class, 'requestNewEmailVerification'])->name('profile.email.new');
Route::post('/profile/email/verify', [ProfileController::class, 'verifyEmailChange'])->name('profile.email.verify');

// ── Admin & Superadmin User Management ──
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/lectures', [AdminUserController::class, 'index'])->name('admin.lectures');
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::get('/admin/users/export', [AdminUserController::class, 'exportUsersCsv'])->name('admin.users.export');
    Route::post('/admin/users/{id}/reset-password', [AdminUserController::class, 'resetPassword'])->name('admin.users.reset');
    Route::post('/admin/users/{id}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('admin.users.toggle-status');

    // ── Superadmin: manage admin accounts ──
    Route::get('/superadmin/admins', function () {
        if (Auth::user()?->role !== 'superadmin') {
            abort(403);
        }
        $admins = User::where('role', 'admin')
            ->orderBy('name')
            ->get();

        return view('pages.admin.manage-admins', compact('admins'));
    })->name('superadmin.admins');

    Route::post('/superadmin/admins/{id}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('superadmin.admins.toggle');

    Route::post('/superadmin/approvals/{user}/override', function (User $user, Request $request) {
        if (Auth::user()?->role !== 'superadmin') {
            abort(403);
        }
        $validated = $request->validate([
            'status' => 'required|in:active,pending,rejected',
            'role' => 'nullable|in:student,teacher,admin',
            'program' => 'nullable|in:teacher,educ,accountancy,psych',
        ]);

        $updates = array_filter($validated, fn ($v) => $v !== null);

        if (($updates['role'] ?? null) === 'student') {
            $candidateProgram = $updates['program'] ?? $user->program;

            if (! in_array($candidateProgram, ['educ', 'accountancy', 'psych'], true)) {
                return redirect()->back()->withErrors(['error' => 'Student role requires a student program.']);
            }

            $updates['program'] = $candidateProgram;
        }

        if (($updates['role'] ?? null) === 'teacher' && isset($updates['program']) && $updates['program'] !== 'teacher') {
            return redirect()->back()->withErrors(['error' => 'Teacher role must use teacher program.']);
        }

        if (($updates['role'] ?? null) === 'admin' && isset($updates['program'])) {
            return redirect()->back()->withErrors(['error' => 'Admin role cannot have a program.']);
        }

        if (($updates['role'] ?? null) === 'teacher') {
            $updates['program'] = 'teacher';
        }
        if (($updates['role'] ?? null) === 'admin') {
            $updates['program'] = null;
        }

        $user->update($updates);

        return redirect()->back()->with('status', "{$user->name} updated.");
    })->name('superadmin.approvals.override');
});

// ── Lectures ──
Route::get('/lectures', [LectureController::class, 'index'])->name('lectures');
Route::get('/teacher/create', [LectureController::class, 'create'])->name('teacher.create');
Route::post('/teacher', [LectureController::class, 'store'])->name('teacher.store');
Route::get('/teacher/{id}/edit', [LectureController::class, 'edit'])->name('teacher.edit');
Route::put('/teacher/{id}', [LectureController::class, 'update'])->name('teacher.update');
Route::delete('/teacher/{id}', [LectureController::class, 'destroy'])->name('teacher.destroy');
Route::get('/teacher/lectures/{id}/file', [LectureController::class, 'download'])->name('teacher.lecture.file');

// ── Class Management ──
Route::get('/manageclass', [ClassManagerController::class, 'index'])->name('manageclass');
Route::post('/classes', [ClassManagerController::class, 'store'])->name('classes.store');
Route::delete('/classes/{class}', [ClassManagerController::class, 'destroy'])->name('classes.destroy');

Route::get('/users/search-students', [ClassManagerController::class, 'searchStudents'])->name('students.search');

Route::post('/classes/{class}/students', [ClassManagerController::class, 'addStudents'])->name('classes.students.add');
Route::delete('/classes/{class}/students/{student}', [ClassManagerController::class, 'removeStudent'])->name('classes.students.remove');
Route::get('/classes/{class}/students', [ClassManagerController::class, 'getClassStudents'])->name('classes.students.get');
Route::get('/classes/{class}/students/search', [ClassManagerController::class, 'searchClassStudents'])->name('classes.students.search');
Route::post('/classes/{class}/invite', [ClassManagerController::class, 'generateInvite'])->name('classes.invite');
Route::post('/classes/{class}/join-link', [ClassManagerController::class, 'generate24HourJoinLink'])->name('classes.join-link');
Route::post('/classes/{class}/modules', [ClassManagerController::class, 'storeModule'])->name('classes.modules.store');
Route::get('/classes/{class}/modules/list', [ClassManagerController::class, 'listModulesJson'])->name('classes.modules.list');
Route::get('/classes/{class}/modules', [ClassManagerController::class, 'listModules'])->name('classes.modules.index');
Route::get('/classes/{class}/modules/show', [ClassManagerController::class, 'showModules'])->name('manage.modules');

Route::middleware('auth')->prefix('test-bank')->name('test-bank.')->group(function () {
    Route::get('/', [TestBankController::class, 'index'])->name('index');
    Route::post('/', [TestBankController::class, 'store'])->name('store');
    Route::put('{testBankQuestion}', [TestBankController::class, 'update'])->name('update');
    Route::patch('{testBankQuestion}/archive', [TestBankController::class, 'archive'])->name('archive');
    Route::post('modules/{module}/questions', [TestBankController::class, 'addToModule'])->name('modules.questions.store');
    Route::get('modules/{module}/questions', [TestBankController::class, 'moduleQuestions'])->name('modules.questions.view');
    Route::post('modules/{module}/import', [TestBankController::class, 'importModuleQuestions'])->name('modules.import');
    Route::get('questions.json', [TestBankController::class, 'questionsJson'])->name('questions.json');
});

// ── Announcements ──
Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index')->middleware('auth');
Route::post('/classes/{class}/announcements', [AnnouncementController::class, 'store'])->name('announcements.store')->middleware('auth');
Route::patch('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update')->middleware('auth');
Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy')->middleware('auth');
Route::post('/announcements/mark-read', [AnnouncementController::class, 'markRead'])->name('announcements.mark-read')->middleware('auth');
Route::get('/classes/{class}/announcements/feed', [AnnouncementController::class, 'classFeed'])->name('announcements.class-feed')->middleware('auth');

// ── Student class/module views ──
Route::get('/my-classes', [ClassManagerController::class, 'myClasses'])->name('student.classes');
Route::get('/my-classes/{class}/modules', [ClassManagerController::class, 'studentModules'])->name('student.modules');

// ── Module actions ──
Route::get('/modules/{module}/view', [ClassManagerController::class, 'viewModuleFile'])->name('module.view');
Route::get('/modules/{module}/pdfjs', [ClassManagerController::class, 'pdfjsViewer'])->name('module.pdfjs');
Route::get('/modules/{module}/public-view', [ClassManagerController::class, 'publicModuleFile'])->name('module.view.public')->middleware('signed');
Route::get('/modules/{module}/view-pdf', [ClassManagerController::class, 'viewPdf'])->name('module.view.pdf');
Route::delete('/modules/{module}', [ClassManagerController::class, 'deleteModule'])->name('modules.delete');
Route::post('/modules/{module}/duplicate', [ClassManagerController::class, 'duplicateModule'])->name('modules.duplicate')->middleware('auth');
Route::post('/modules/{module}/toggle-status', [ClassManagerController::class, 'toggleModuleStatus'])->name('modules.toggle-status')->middleware('auth');
Route::post('/modules/{module}/progress', [ClassManagerController::class, 'updateProgress'])->name('modules.progress.update');

// ── Quiz ──
Route::middleware('auth')->group(function () {
    Route::get('/modules/{module}/quiz/create', [ClassManagerController::class, 'createQuiz'])->name('quiz.create');
    Route::post('/modules/{module}/quiz/generate', [ClassManagerController::class, 'generateQuizAi'])->name('quiz.generate');
    Route::post('/modules/{module}/quiz/store', [ClassManagerController::class, 'storeQuizManual'])->name('quiz.store');
    Route::get('/modules/{module}/quiz/questions', [QuizController::class, 'getQuestions'])->name('quiz.get.questions');
    Route::post('/modules/{module}/quiz/submit', [QuizController::class, 'submitQuiz'])->name('quiz.submit');
    Route::post('/modules/{module}/quiz/insights', [QuizController::class, 'generateInsights'])->name('quiz.insights');
    Route::get('/modules/{module}/quiz/history', [QuizController::class, 'attemptHistory'])->name('quiz.history');
    Route::get('/modules/{module}/quiz/analysis', [QuizController::class, 'latestAttemptAnalysis'])->name('quiz.analysis');
    Route::get('/quiz/attempts/{snapshot}/detail', [QuizController::class, 'attemptSnapshotDetail'])->name('quiz.attempt.detail');
    Route::post('/quiz/{module}/answer', [QuizController::class, 'submitAnswer'])->name('quiz.answer');
    Route::post('/quiz/create-draft/{class}', [QuizController::class, 'createQuizDraft'])->name('quiz.create.draft');
    Route::delete('/modules/{module}/quiz/attempts', [QuizController::class, 'resetAttempts'])->name('quiz.attempts.reset');
    Route::delete('/modules/{module}/quiz/my-attempt', [QuizController::class, 'resetMyAttempt'])->name('quiz.my.attempt.reset');
});

// ── Student Performance ──
Route::get('/student-performance/{class}', [PerformanceController::class, 'studentPerformance'])->name('student.performance');
Route::get('/student-performance/{class}/progress', [PerformanceController::class, 'classProgressTracker'])->name('student.progress.tracker');
Route::get('/student-performance/{class}/students/{student}', [PerformanceController::class, 'studentItemAnalysis'])->name('student.performance.student-item-analysis');
Route::post('/student-performance/{class}/refresh-ai', [PerformanceController::class, 'refreshAiSummary'])->name('student.performance.refresh');
Route::post('/student-performance/{class}/assessment-refresh-ai', [PerformanceController::class, 'refreshAssessmentAiSummary'])->name('student.performance.assessment.refresh');
Route::get('/student-performance/{class}/assessment-analysis/{student}', [PerformanceController::class, 'studentAssessmentAnalysis'])->name('student.assessment.analysis');
Route::post('/student-performance/{class}/assessment-analysis/{student}/generate-ai', [PerformanceController::class, 'generateAssessmentAiAnalysis'])->name('student.assessment.analysis.generate-ai');

// ── Chat ──
Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
Route::get('/chat/conversations', [ChatController::class, 'conversations'])->name('chat.conversations');
Route::get('/chat/conversations/{chat}/messages', [ChatController::class, 'messages'])->name('chat.messages');
Route::post('/chat/conversations', [ChatController::class, 'start'])->name('chat.start');
Route::post('/chat/conversations/{chat}/messages', [ChatController::class, 'sendMessage'])->name('chat.send_message');
Route::post('/chat/conversations/{chat}/remove', [ChatController::class, 'remove'])->name('chat.conversations.remove');

// ── User Search ──
Route::get('/users/search', [ClassManagerController::class, 'searchUsers'])->name('users.search.api');
Route::get('/users/search-page', [ClassManagerController::class, 'searchPage'])->name('users.search');

// ── Misc ──
Route::get('/teacher-dashboard-teachh', fn () => view('pages.teacher.teachh'))->name('teacherDashboardTeachh');
Route::get('/test-ai-laravel', [TestAiController::class, 'test'])->name('test.ai.laravel');
// Profile — program
Route::post('/profile/program', [ProfileController::class, 'updateProgram'])->name('profile.program.update');
// Admin — unlock a user's program
Route::post('/admin/users/{user}/unlock-program', [ProfileController::class, 'unlockProgram'])->name('admin.unlock.program');

// AI Settings
Route::get('/admin/ai-settings/classes', [AiSettingsController::class, 'classes'])->name('admin.class-ai-settings');
Route::post('/admin/ai-settings/classes/{class}', [AiSettingsController::class, 'updateClass'])->name('admin.class-ai-settings.update');
Route::get('/superadmin/ai-settings', [AiSettingsController::class, 'global'])->name('superadmin.ai-settings');
Route::post('/superadmin/ai-settings', [AiSettingsController::class, 'updateGlobal'])->name('superadmin.ai-settings.update');

Route::get('/admin/approvals', [AdminApprovalController::class, 'index'])->name('admin.approvals');
Route::post('/admin/approvals/{user}/approve', [AdminApprovalController::class, 'approve'])->name('admin.approvals.approve');
Route::post('/admin/approvals/approve-many', [AdminApprovalController::class, 'approveMany'])->name('admin.approvals.approve-many');
Route::post('/admin/approvals/approve-all', [AdminApprovalController::class, 'approveAll'])->name('admin.approvals.approve-all');
Route::post('/admin/approvals/{user}/reject', [AdminApprovalController::class, 'reject'])->name('admin.approvals.reject');

// ── Mock Boards ──

// Batch Analytics (CORRECTED ROUTE per corrections-v2.md)
// Must be FIRST to avoid {mock_board} catch-all routes intercepting 'batch-analytics'
// TODO: Restore 'can:view-batch-analytics' middleware after the authorization gate is fixed
Route::prefix('mock-boards/batch-analytics')->middleware('auth')->group(function () {
    Route::get('/', [BatchAnalyticsController::class, 'dashboard'])->name('mock-boards.batch.dashboard');
    Route::get('{program}/{mock_board}', [BatchAnalyticsController::class, 'mockBoardsAnalysis'])->name('mock-boards.batch.analysis');
    Route::get('{program}/{mock_board}/student-analysis/{user}', [BatchAnalyticsController::class, 'studentItemAnalysis'])->name('mock-boards.batch.student-analysis');
    Route::post('{program}/{mock_board}/compute-anova', [BatchAnalyticsController::class, 'computeANOVA'])->name('mock-boards.batch.anova.compute');
});

// Teacher Mock Boards (owned per-teacher, program-scoped)
Route::middleware(['auth'])->prefix('student/mock-boards')->name('student.mock-boards.')->group(function () {
    Route::get('/', [StudentMockBoardController::class, 'index'])->name('index');
    Route::post('/', [StudentMockBoardController::class, 'store'])->name('store');
    Route::put('{mock_board}', [StudentMockBoardController::class, 'update'])->name('update');
    Route::delete('{mock_board}', [StudentMockBoardController::class, 'destroy'])->name('destroy');
    Route::post('{mock_board}/phases/add', [StudentMockBoardController::class, 'addPhase'])->name('phases.add');
    Route::post('{mock_board}/phases', [StudentMockBoardController::class, 'updatePhases'])->name('phases.update');

    Route::get('{mock_board}', [StudentMockBoardController::class, 'show'])->name('show');
    Route::get('{mock_board}/{mock_board_phase}/take', [StudentMockBoardController::class, 'take'])->name('take');
    Route::post('{mock_board}/{mock_board_phase}/submit', [StudentMockBoardController::class, 'submit'])->name('submit');
    Route::post('{mock_board}/{mock_board_phase}/insights', [StudentMockBoardController::class, 'insights'])->name('insights');
    Route::get('{mock_board}/results', [StudentMockBoardController::class, 'results'])->name('results');
    Route::get('{mock_board}/readiness', [StudentMockBoardController::class, 'readiness'])->name('readiness');
    Route::get('{mock_board}/readiness/export', [StudentMockBoardController::class, 'exportReadinessExcel'])->name('readiness.export');
});
// Admin — Mock Board Approvals
Route::middleware(['auth'])->prefix('admin/mock-boards')->name('admin.mock-boards.')->group(function () {
    Route::get('/approvals', [MockBoardApprovalController::class, 'index'])->name('approvals');
    Route::get('/{mock_board}/questions', [MockBoardApprovalController::class, 'questions'])->name('questions');
    Route::post('/{mock_board}/approve', [MockBoardApprovalController::class, 'approve'])->name('approve');
    Route::post('/{mock_board}/reject', [MockBoardApprovalController::class, 'reject'])->name('reject');
});
// Historical board/licensure exam results — CRUD is admin/superadmin-gated
// inside the controller; index is readable by any teacher so they can pick
// a record to link on their own mock board for comparison.
Route::middleware(['auth'])->prefix('historical-board-exams')->name('historical-board-exams.')->group(function () {
    Route::get('/', [HistoricalBoardExamController::class, 'index'])->name('index');
    Route::post('/', [HistoricalBoardExamController::class, 'store'])->name('store');
    Route::put('/{historical_board_exam_result}', [HistoricalBoardExamController::class, 'update'])->name('update');
    Route::delete('/{historical_board_exam_result}', [HistoricalBoardExamController::class, 'destroy'])->name('destroy');
});
Route::middleware(['auth'])->post('student/mock-boards/{mock_board}/link-historical-exam', [HistoricalBoardExamController::class, 'link'])->name('student.mock-boards.link-historical-exam');
Route::middleware(['auth'])->post('mock-boards/{mock_board}/quick-benchmark', [HistoricalBoardExamController::class, 'quickBenchmark'])->name('mock-boards.quick-benchmark');
// Idagdag ito sa web.php
Route::get('/pre-assessments', [StudentAssessmentController::class, 'preassessments'])->name('student.preassessments')->middleware('auth');

Route::middleware(['auth'])->group(function () {
    Route::get('/announcements/{announcement}/comments', [AnnouncementController::class, 'comments'])->name('announcements.comments');
    Route::post('/announcements/{announcement}/comments', [AnnouncementController::class, 'storeComment'])->name('announcements.comments.store');
});

Route::post('/modules/{module}/quiz/start', [QuizController::class, 'startAttempt'])->name('quiz.start');
Route::put('/modules/{module}/quiz/max-attempts', [QuizController::class, 'updateMaxAttempts'])->name('quiz.max-attempts.update');
Route::put('/modules/{module}/settings', [QuizController::class, 'updateModuleSettings'])->name('modules.settings.update');
Route::post('/modules/{module}/quiz/grant-attempt/{student}', [QuizController::class, 'grantExtraAttempt'])->name('quiz.grant.attempt');

// ── Module Sub-Parts (teacher management) ──
Route::get('/modules/{module}/subparts', [ModuleSubpartController::class, 'index'])->name('module.subparts.index');
Route::post('/modules/{module}/subparts', [ModuleSubpartController::class, 'store'])->name('module.subparts.store');
Route::put('/subparts/{subpart}', [ModuleSubpartController::class, 'update'])->name('module.subparts.update');
Route::post('/modules/{module}/subparts/reorder', [ModuleSubpartController::class, 'reorder'])->name('module.subparts.reorder');
Route::delete('/subparts/{subpart}', [ModuleSubpartController::class, 'destroy'])->name('module.subparts.destroy');

// ── Module Sub-Parts (student view + progress) ──
Route::get('/modules/{module}/subparts/student', [ModuleSubpartController::class, 'studentIndex'])->name('module.subparts.student.index');
Route::post('/subparts/{subpart}/progress', [ModuleSubpartController::class, 'updateProgress'])->name('module.subparts.progress.update');
Route::get('/subparts/{subpart}/view', [ModuleSubpartController::class, 'viewFile'])->name('module.subparts.view');
Route::get('/subparts/{subpart}/pdfjs', [ModuleSubpartController::class, 'pdfjsViewer'])->name('module.subparts.pdfjs');
Route::get('/subparts/{subpart}/docxjs', [ModuleSubpartController::class, 'docxViewer'])->name('module.subparts.docxjs');

// ── Lessons within Module Sub-Parts ──
Route::get('/subparts/{subpart}/lessons', [SubpartLessonController::class, 'index'])->name('subpart.lessons.index');
Route::post('/subparts/{subpart}/lessons', [SubpartLessonController::class, 'store'])->name('subpart.lessons.store');
Route::put('/lessons/{lesson}', [SubpartLessonController::class, 'update'])->name('subpart.lessons.update');
Route::post('/subparts/{subpart}/lessons/reorder', [SubpartLessonController::class, 'reorder'])->name('subpart.lessons.reorder');
Route::delete('/lessons/{lesson}', [SubpartLessonController::class, 'destroy'])->name('subpart.lessons.destroy');
Route::get('/subparts/{subpart}/lessons/student', [SubpartLessonController::class, 'studentIndex'])->name('subpart.lessons.student.index');
Route::post('/lessons/{lesson}/progress', [SubpartLessonController::class, 'updateProgress'])->name('subpart.lessons.progress.update');
Route::get('/lessons/{lesson}/view', [SubpartLessonController::class, 'viewFile'])->name('subpart.lessons.view');
Route::get('/lessons/{lesson}/docxjs', [SubpartLessonController::class, 'docxViewer'])->name('subpart.lessons.docxjs');

Route::get('/classes/{class}/modules/lectures', [ClassManagerController::class, 'listLectureModulesJson'])->name('classes.modules.lectures');
Route::get('/classes/{class}/modules/list', [ClassManagerController::class, 'listModulesJson'])->name('classes.modules.list');
