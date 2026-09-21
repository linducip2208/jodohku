<?php

return [
    'features' => [
        'ai' => env('FEATURE_AI', true),
        'virtual' => env('FEATURE_VIRTUAL', true),
        'video_call' => env('FEATURE_VIDEO_CALL', false),
        'community' => env('FEATURE_COMMUNITY', true),
        'credits' => env('FEATURE_CREDITS', true),
        'gifts' => env('FEATURE_GIFTS', true),
        'boost' => env('FEATURE_BOOST', true),
        'ads' => env('FEATURE_ADS', true),
        'verification' => env('FEATURE_VERIFICATION', true),
    ],

    'limits' => [
        'free_daily_likes' => env('FREE_DAILY_LIKES', 20),
        'free_daily_super_likes' => 1,
        'rewind_cooldown_minutes' => 5,
    ],
];
