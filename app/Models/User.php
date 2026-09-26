<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'idnumber',
        'email',
        'name',
        'password',
        'role',
        'email_verified_at',
        'last_seen_at',
        'program',
        'program_locked',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'program_locked' => 'boolean',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * True if the user was active within the last 3 minutes.
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(3));
    }

    /**
     * Human-readable last-seen label for the frontend.
     * Returns "Online" or "Last seen X ago".
     */
    public function lastSeenLabel(): string
    {
        if ($this->isOnline()) {
            return 'Online';
        }

        if ($this->last_seen_at === null) {
            return 'Never seen';
        }

        return 'Last seen '.$this->last_seen_at->diffForHumans();
    }

    public function username()
    {
        return 'idnumber';
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function classes()
    {
        return $this->belongsToMany(ClassModel::class, 'class_user', 'user_id', 'class_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function moduleProgress()
    {
        return $this->hasMany(ModuleProgress::class, 'user_id');
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function testBankQuestions(): HasMany
    {
        return $this->hasMany(TestBankQuestion::class, 'created_by');
    }

    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class, 'chat_user', 'user_id', 'chat_id')
            ->withTimestamps();
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id')->orderBy('created_at');
    }

    /**
     * Check if the user currently has an active, in-progress formal assessment.
     * When $classId is provided, checks if the active assessment is in that class.
     */
    public function hasActiveFormalAssessment(?int $classId = null): bool
    {
        return $this->getActiveFormalAssessment($classId) !== null;
    }

    /**
     * Get the active in-progress assessment attempt if any.
     */
    public function getActiveFormalAssessment(?int $classId = null): ?QuizAttempt
    {
        $query = $this->quizAttempts()
            ->where('status', 'in_progress')
            ->where(function ($q) {
                $q->whereHas('module', function ($m) {
                    $m->where('is_formal_assessment', true)
                        ->orWhereIn('quiz_stage', ['pre_test', 'post_test'])
                        ->orWhereIn('assessment_purpose', ['pre_test', 'post_test']);
                })
                    ->orWhereNotNull('mock_board_id')
                    ->orWhereIn('quiz_stage', ['pre_test', 'post_test']);
            });

        if ($classId !== null) {
            $query->whereHas('module', function ($m) use ($classId) {
                $m->where('class_id', $classId);
            });
        }

        return $query->where('updated_at', '>=', now()->subHours(4))
            ->with('module')
            ->latest('updated_at')
            ->first();
    }
}
