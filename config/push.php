<?php

return [
    'default' => env('PUSH_DRIVER', 'log'),

    'fcm' => [
        // Legacy server key (deprecated by Google, kept as last-resort fallback).
        'server_key' => env('PUSH_FCM_SERVER_KEY', ''),
        // FCM v1 (recommended): project id + service-account JSON (path or raw JSON).
        'project_id' => env('PUSH_FCM_PROJECT_ID', ''),
        'service_json' => env('PUSH_FCM_SERVICE_JSON', ''),
    ],
];
