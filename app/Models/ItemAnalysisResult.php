<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAnalysisResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'question_id',
        'quiz_stage',
        'question_text',
        'correct_option',
        'ai_difficulty_label',
        'n_examinees',
        'n_correct',
        'p_index',
        'd_index',
        'point_biserial',
        'option_stats',
        'distractor_efficiency',
        'difficulty_interpretation',
        'discrimination_interpretation',
        'empirical_band',
        'label_matches',
        'verdict',
        'flags',
        'confidence',
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
            'option_stats' => 'array',
            'flags' => 'array',
            'label_matches' => 'boolean',
            'p_index' => 'float',
            'd_index' => 'float',
            'point_biserial' => 'float',
            'distractor_efficiency' => 'float',
            'analyzed_at' => 'datetime',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}
