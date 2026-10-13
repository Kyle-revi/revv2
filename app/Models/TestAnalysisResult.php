<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestAnalysisResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'quiz_stage',
        'n_examinees',
        'n_items',
        'mean_score',
        'sd_score',
        'score_variance',
        'kr20',
        'kr20_interpretation',
        'mean_p',
        'mean_d',
        'label_accuracy',
        'mean_p_by_label',
        'verdict_counts',
        'analyzed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mean_p_by_label' => 'array',
            'verdict_counts' => 'array',
            'mean_score' => 'float',
            'sd_score' => 'float',
            'score_variance' => 'float',
            'kr20' => 'float',
            'mean_p' => 'float',
            'mean_d' => 'float',
            'label_accuracy' => 'float',
            'analyzed_at' => 'datetime',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
