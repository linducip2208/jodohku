<?php

return [
    'site_name' => env('SEO_SITE_NAME', 'Jodohku'),
    'default_title' => env('SEO_DEFAULT_TITLE', 'Jodohku — Temukan Jodohmu'),
    'default_description' => env('SEO_DEFAULT_DESCRIPTION', 'Platform biro jodoh modern Indonesia: matchmaking, chat aman, verifikasi, virtual member transparan.'),
    'canonical_url' => env('SEO_CANONICAL_URL', env('APP_URL', 'http://localhost:8000')),
    'robots_index' => env('SEO_ROBOTS_INDEX', true),
    'og' => [
        'type' => 'website',
        'locale' => 'id_ID',
    ],
];
