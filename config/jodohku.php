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

    'boost' => [
        'post_cost' => env('BOOST_POST_COST', 50),
    ],

    'virtual' => [
        'max_daily_messages' => env('VIRTUAL_MAX_DAILY', 5),
        'active_from_hour' => env('VIRTUAL_ACTIVE_FROM', 8),
        'active_until_hour' => env('VIRTUAL_ACTIVE_UNTIL', 22),
    ],

    'referrals' => [
        'daily_cap' => env('REFERRAL_DAILY_CAP', 20),
        'reward_credits' => env('REFERRAL_REWARD_CREDITS', 100),
    ],

    'affiliates' => [
        'default_rate' => env('AFFILIATE_DEFAULT_RATE', 0.10),
    ],

    'webrtc' => [
        'stun' => env('WEBRTC_STUN', 'stun:stun.l.google.com:19302'),
        'turn_url' => env('WEBRTC_TURN_URL', ''),
        'turn_username' => env('WEBRTC_TURN_USERNAME', ''),
        'turn_credential' => env('WEBRTC_TURN_CREDENTIAL', ''),
    ],
];
