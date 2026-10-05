<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MockBoard extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'teacher_id',
        'title',
        'description',
        'program',
        'review_period_start',
        'review_period_end',
        'passing_percentage',
        'historical_board_exam_result_id',
        'visibility',
        'visible_to',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'review_period_start' => 'date',
        'review_period_end' => 'date',
        'passing_percentage' => 'integer',
        'approved_at' => 'datetime',
        'visible_to' => 'array',
    ];

    /**
     * Optional class relation (if linked to a specific class).
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class);
    }

    /**
     * The teacher who owns this mock board.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * The admin who approved/rejected this mock board.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The real/historical licensure exam this board is compared against,
     * if the teacher has linked one.
     */
    public function historicalBoardExamResult(): BelongsTo
    {
        return $this->belongsTo(HistoricalBoardExamResult::class);
    }

    /**
     * The phases (Pre-Test and Pre-Boards) for this mock board.
     */
    public function phases(): HasMany
    {
        return $this->hasMany(MockBoardPhase::class);
    }

    /**
     * All student attempts for this mock board.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(MockBoardAttempt::class);
    }

    /**
     * Statistics (ANOVA) for this mock board.
     */
    public function statistics(): HasOne
    {
        return $this->hasOne(MockBoardStatistic::class);
    }

    /**
     * Questions linked directly to this mock board.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'mock_board_id');
    }

    /**
     * Student readiness reports generated for this mock board.
     */
    public function readinessReports(): HasMany
    {
        return $this->hasMany(MockBoardReadinessReport::class);
    }

    /**
     * Check if this mock board is currently active (within review period).
     */
    public function isActive(): bool
    {
        $today = now()->toDateString();

        return $this->review_period_start <= $today && $this->review_period_end >= $today;
    }

    /**
     * Approval status helpers.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function markApproved(User $user): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function markRejected(User $user, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function resetToPending(): void
    {
        $this->update([
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Scope: Only ongoing mock boards (within review period).
     */
    public function scopeOngoing($query)
    {
        $today = now()->toDateString();

        return $query->where('review_period_start', '<=', $today)
            ->where('review_period_end', '>=', $today);
    }

    /**
     * Scope: Only ended mock boards (past review period).
     */
    public function scopeEnded($query)
    {
        return $query->where('review_period_end', '<', now()->toDateString());
    }

    /**
     * Scope: Filter by program (e.g., 'psychology', 'accountancy').
     */
    public function scopeByProgram($query, string $program)
    {
        return $query->where('program', $program);
    }

    /**
     * Scope: Only approved mock boards.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Only pending mock boards.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
