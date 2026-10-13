<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id', 'title', 'description', 'file_path', 'file_type', 'order',
        'is_quiz', 'is_assignment', 'is_lecture', 'is_formal_assessment', 'time_limit', 'passing_grade', 'visibility',
        'created_by', 'is_mock_board', 'due_date', 'available_at', 'is_active', 'max_attempts', 'quiz_stage',
    ];

    protected $casts = [
        'is_quiz' => 'boolean',
        'is_assignment' => 'boolean',
        'is_lecture' => 'boolean',
        'quiz_stage' => 'string',
        'is_formal_assessment' => 'boolean',
        'passing_grade' => 'integer',
        'max_attempts' => 'integer',
        'due_date' => 'datetime',
        'available_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * True kung may due date na, at lagpas na ang oras ngayon.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast();
    }

    /**
     * True kung ang activation date ay sa hinaharap pa (hindi pa bukas).
     */
    public function isUpcoming(): bool
    {
        return (bool) $this->is_active && $this->available_at !== null && $this->available_at->isFuture();
    }

    /**
     * True kung bukas at active ang assessment/quiz para sagutan.
     */
    public function isOpen(): bool
    {
        if (! $this->is_quiz && ! $this->is_formal_assessment) {
            return true;
        }

        return (bool) $this->is_active && ($this->available_at === null || $this->available_at->isPast()) && ! $this->isOverdue();
    }

    /**
     * True kung sarado na o inactive.
     */
    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * Human-readable status label.
     */
    public function statusLabel(): string
    {
        if (! $this->is_quiz && ! $this->is_formal_assessment) {
            return 'Active';
        }

        if (! $this->is_active) {
            return 'Closed';
        }

        if ($this->isOverdue()) {
            return 'Closed';
        }

        if ($this->isUpcoming()) {
            return 'Upcoming';
        }

        return 'Open';
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(ModuleProgress::class);
    }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    /**
     * Pre-test questions only, for lecture-style modules. Ordinary standalone
     * quizzes / mock board phase quizzes have quiz_stage = null and will not
     * appear here.
     */
    public function preTestQuestions(): HasMany
    {
        return $this->quizQuestions()->where('quiz_stage', 'pre_test');
    }

    /**
     * Post-test questions only, for lecture-style modules.
     */
    public function postTestQuestions(): HasMany
    {
        return $this->quizQuestions()->where('quiz_stage', 'post_test');
    }

    public function hasPreTest(): bool
    {
        return $this->preTestQuestions()->exists();
    }

    public function hasPostTest(): bool
    {
        return $this->postTestQuestions()->exists();
    }

    /**
     * Ordered content sub-parts — this is the entire content stage for a
     * lecture-style module. There is no separate module-level file/content
     * fallback: file_path/description above stay on the model for backward
     * compatibility with non-lecture module types (standalone quizzes, mock
     * board phase modules) but are not used as lecture content anymore.
     */
    public function subparts(): HasMany
    {
        return $this->hasMany(ModuleSubpart::class)->orderBy('order');
    }

    public function visibleTo(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'module_user_visibility', 'module_id', 'user_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function attemptGrants(): HasMany
    {
        return $this->hasMany(AssessmentAttemptGrant::class);
    }

    public function allowedAttemptsFor(int $userId): int
    {
        $grants = $this->relationLoaded('attemptGrants')
            ? $this->attemptGrants->where('user_id', $userId)->sum('extra_attempts')
            : $this->attemptGrants()->where('user_id', $userId)->sum('extra_attempts');

        return ($this->max_attempts ?? 1) + $grants;
    }

    public function itemAnalysisResults(): HasMany
    {
        return $this->hasMany(ItemAnalysisResult::class);
    }

    public function testAnalysisResults(): HasMany
    {
        return $this->hasMany(TestAnalysisResult::class);
    }
}
