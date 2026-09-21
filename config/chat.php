<?php

return [
    'rate_limits' => [
        'messages_per_minute' => env('CHAT_MSG_PER_MIN', 20),
        'messages_per_hour' => env('CHAT_MSG_PER_HOUR', 200),
        'requests_per_day' => env('CHAT_REQUESTS_PER_DAY', 30),
        'premium' => [
            'messages_per_minute' => env('CHAT_PREMIUM_MSG_PER_MIN', 60),
            'messages_per_hour' => env('CHAT_PREMIUM_MSG_PER_HOUR', 1000),
            'requests_per_day' => env('CHAT_PREMIUM_REQUESTS_PER_DAY', 200),
        ],
    ],

    'message' => [
        'max_length' => env('CHAT_MAX_LENGTH', 2000),
        'allow_attachments' => true,
        'max_attachments' => 5,
    ],

    'chat_request' => [
        'expiry_hours' => 72,
        'require_request_without_match' => false,
    ],

    'typing' => [
        'broadcast' => true,
        'ttl_seconds' => 5,
    ],

    'unread_counter_cache' => true,
];
