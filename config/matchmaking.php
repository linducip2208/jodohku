<?php

return [
    'weights' => [
        'age' => env('MATCH_WEIGHT_AGE', 10),
        'location' => env('MATCH_WEIGHT_LOCATION', 10),
        'preference' => env('MATCH_WEIGHT_PREFERENCE', 20),
        'personality' => env('MATCH_WEIGHT_PERSONALITY', 20),
        'interest' => env('MATCH_WEIGHT_INTEREST', 10),
        'lifestyle' => env('MATCH_WEIGHT_LIFESTYLE', 10),
        'goal' => env('MATCH_WEIGHT_GOAL', 10),
        'behavior' => env('MATCH_WEIGHT_BEHAVIOR', 10),
    ],

    'hard_filters' => [
        'exclude_self' => true,
        'exclude_blocked' => true,
        'require_active' => true,
        'enforce_age_range' => true,
        'enforce_gender_preference' => true,
        'max_distance_default_km' => env('MATCH_MAX_DISTANCE_KM', 200),
    ],

    'thresholds' => [
        'min_mutual_for_match' => env('MATCH_MIN_MUTUAL', 0),
        'auto_match_on_mutual_like' => true,
        'min_score_for_daily_pick' => env('MATCH_MIN_DAILY_SCORE', 55),
        'daily_picks' => env('MATCH_DAILY_PICKS', 10),
    ],

    'recompute' => [
        'on_profile_update' => true,
        'batch_size' => 200,
        'ttl_hours' => 24,
    ],
];
