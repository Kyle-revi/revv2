<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option',
        'answer',
        'is_correct',
    ];

    public function setAnswerAttribute($value): void
    {
        $this->attributes['selected_option'] = $value;
    }

    public function getAnswerAttribute(): ?string
    {
        return $this->attributes['selected_option'] ?? null;
    }

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class);
    }

    public function question()
    {
        return $this->belongsTo(QuizQuestion::class);
    }
}
