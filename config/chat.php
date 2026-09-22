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

    'attachments' => [
        // Private disk: files are served only through the authorized
        // download route, never via direct public URLs.
        'disk' => env('CHAT_ATTACHMENTS_DISK', 'chat'),
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

    'themes' => [
        ['code' => 'default', 'name' => 'Default', 'bubble_me' => '#ec4899', 'bubble_them' => '#f1f1f4'],
        ['code' => 'ocean', 'name' => 'Ocean', 'bubble_me' => '#0ea5e9', 'bubble_them' => '#e0f2fe'],
        ['code' => 'sunset', 'name' => 'Sunset', 'bubble_me' => '#f59e0b', 'bubble_them' => '#fef3c7'],
        ['code' => 'forest', 'name' => 'Hutan', 'bubble_me' => '#16a34a', 'bubble_them' => '#dcfce7'],
        ['code' => 'midnight', 'name' => 'Midnight', 'bubble_me' => '#6366f1', 'bubble_them' => '#1e1b4b'],
    ],

    'stickers' => [
        ['code' => 'love', 'emoji' => '❤️', 'name' => 'Love'],
        ['code' => 'kiss', 'emoji' => '😘', 'name' => 'Kiss'],
        ['code' => 'flower', 'emoji' => '🌹', 'name' => 'Mawar'],
        ['code' => 'laugh', 'emoji' => '😂', 'name' => 'Ngakak'],
        ['code' => 'sad', 'emoji' => '🥺', 'name' => 'Memelas'],
        ['code' => 'angry', 'emoji' => '😡', 'name' => 'Marah'],
        ['code' => 'cool', 'emoji' => '😎', 'name' => 'Cool'],
        ['code' => 'party', 'emoji' => '🎉', 'name' => 'Party'],
        ['code' => 'coffee', 'emoji' => '☕', 'name' => 'Ngopi'],
        ['code' => 'moon', 'emoji' => '🌙', 'name' => 'Selamat malam'],
        ['code' => 'sun', 'emoji' => '☀️', 'name' => 'Selamat pagi'],
        ['code' => 'hug', 'emoji' => '🤗', 'name' => 'Peluk'],
    ],
];
