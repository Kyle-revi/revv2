<?php

namespace App\Models;

use App\Services\MockBoardReadinessService;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function casts(): array
    {
        return [
            'readiness_percentage' => 'float',
            'gap_percentage' => 'float',
            'gap_items' => 'integer',
            'pre_test_score' => 'float',
            'post_test_score' => 'float',
            'improvement_percentage' => 'float',
            'item_insights' => 'array',
            'peer_benchmark' => 'array',
            'historical_comparison' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Accessor and mutator for domain_breakdown.
     * Sanitizes weakest_domain to null when the student has 100% mastery.
     */
    protected function domainBreakdown(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $decoded = is_string($value) ? json_decode($value, true) : $value;
                if (! is_array($decoded)) {
                    return ['list' => []];
                }

                $list = $decoded['list'] ?? [];
                if (! empty($list)) {
                    $all100 = collect($list)->every(fn ($d) => ($d['post_score'] ?? 0) >= 100);
                    if ($all100 || (float) $this->readiness_percentage >= 100) {
                        $decoded['weakest_domain'] = null;
                    }
                }

                return $decoded;
            },
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    /**
     * Accessor and mutator for ai_action_plan.
     * Cleanses hallucinated domains/topics and ensures Philippine licensure alignment.
     */
    protected function aiActionPlan(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $decoded = is_string($value) ? json_decode($value, true) : $value;
                if (! is_array($decoded)) {
                    return [];
                }

                $domains = $this->domain_breakdown['list'] ?? [];
                $all100 = ! empty($domains) && collect($domains)->every(fn ($d) => ($d['post_score'] ?? 0) >= 100);
                $isPerfect = ((float) $this->readiness_percentage >= 100) || $all100;

                if ($isPerfect) {
                    $decoded['priority_domains'] = [];
                    $decoded['review_topics'] = [];

                    $steps = (array) ($decoded['study_steps'] ?? []);
                    $hasUsHallucination = false;
                    foreach ($steps as $st) {
                        if (preg_match('/ASC\s*\d+|AICPA|US\s*GAAP/i', (string) $st)) {
                            $hasUsHallucination = true;
                            break;
                        }
                    }

                    if ($hasUsHallucination || empty($steps)) {
                        $decoded['study_steps'] = [
                            '1. Maintain Mastery Through Spaced Retrieval: Schedule periodic active recall quizzes to retain theoretical frameworks and computational agility across all tested Philippine CPA syllabus topics.',
                            '2. Pacing and Time Management: Practice complete timed mock board simulations (3 hours per subject) to master pacing, time allocation per problem, and exam-day speed.',
                            '3. Stay Updated with Latest Regulatory Issuances: Review the latest BIR revenue regulations, PRC Board of Accountancy updates, and newly effective PFRS/PAS amendments.',
                            '4. Simulate Actual Licensure Exam Conditions: Rehearse under strict PRC CPALE examination conditions (non-programmable calculators, standard scratch paper, uninterrupted 3-hour blocks) to maximize mental stamina.',
                        ];
                    }
                } else {
                    if (! empty($domains) && ! empty($decoded['priority_domains'])) {
                        $validNames = collect($domains)
                            ->filter(fn ($d) => ($d['post_score'] ?? 0) < 100)
                            ->pluck('domain')
                            ->all();

                        $decoded['priority_domains'] = array_values(array_filter(
                            (array) $decoded['priority_domains'],
                            fn ($dom) => in_array($dom, $validNames, true)
                        ));
                    }
                }

                // Cleanse narrative and study steps into direct second-person address ("You" / "Your")
                if (! empty($decoded['summary_narrative'])) {
                    $decoded['summary_narrative'] = MockBoardReadinessService::sanitizeToSecondPerson((string) $decoded['summary_narrative']);
                }

                if (! empty($decoded['study_steps']) && is_array($decoded['study_steps'])) {
                    $decoded['study_steps'] = array_map(
                        fn ($step) => MockBoardReadinessService::sanitizeToSecondPerson((string) $step),
                        $decoded['study_steps']
                    );
                }

                return $decoded;
            },
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

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
