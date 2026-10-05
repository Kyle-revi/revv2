<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockBoardReadinessReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mock_board_id',
        'readiness_percentage',
        'tier',
        'tier_label',
        'gap_percentage',
        'gap_items',
        'pre_test_score',
        'post_test_score',
        'improvement_percentage',
        'consistency_status',
        'domain_breakdown',
        'item_insights',
        'peer_benchmark',
        'historical_comparison',
        'ai_action_plan',
        'generated_at',
    ];

    protected $casts = [
        'readiness_percentage' => 'float',
        'gap_percentage' => 'float',
        'gap_items' => 'integer',
        'pre_test_score' => 'float',
        'post_test_score' => 'float',
        'improvement_percentage' => 'float',
        'domain_breakdown' => 'array',
        'item_insights' => 'array',
        'peer_benchmark' => 'array',
        'historical_comparison' => 'array',
        'ai_action_plan' => 'array',
        'generated_at' => 'datetime',
    ];

    /**
     * The student this readiness report belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The mock board this report assesses.
     */
    public function mockBoard(): BelongsTo
    {
        return $this->belongsTo(MockBoard::class);
    }
}
