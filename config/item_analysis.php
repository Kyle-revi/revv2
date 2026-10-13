<?php

return [
    'group_fraction' => 0.27,
    'attempt_policy' => env('ITEM_ANALYSIS_ATTEMPT_POLICY', 'first'), // first | best | latest
    'min_n_insufficient' => 10,
    'min_n_full_confidence' => 30,
    'functional_distractor_min_rate' => 0.05,
    'p_bands' => [
        ['min' => 0.00, 'max' => 0.20, 'label' => 'Very Difficult', 'action' => 'revise'],
        ['min' => 0.21, 'max' => 0.40, 'label' => 'Difficult', 'action' => 'retain'],
        ['min' => 0.41, 'max' => 0.60, 'label' => 'Average', 'action' => 'retain'],
        ['min' => 0.61, 'max' => 0.80, 'label' => 'Easy', 'action' => 'retain'],
        ['min' => 0.81, 'max' => 1.00, 'label' => 'Very Easy', 'action' => 'revise'],
    ],
    'd_bands' => [
        ['min' => 0.40, 'max' => 1.00, 'label' => 'Very good'],
        ['min' => 0.30, 'max' => 0.39, 'label' => 'Reasonably good'],
        ['min' => 0.20, 'max' => 0.29, 'label' => 'Marginal'],
        ['min' => 0.00, 'max' => 0.19, 'label' => 'Poor'],
        ['min' => -1.00, 'max' => -0.01, 'label' => 'Negative'],
    ],
    'empirical_bands' => [
        'difficult_max' => 0.40,
        'easy_min' => 0.71,
    ],
];
