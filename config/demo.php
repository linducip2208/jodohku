<?php

return [
    /*
    | Demo dataset defaults (php artisan jodohku:demo). All overridable
    | per-run via command options. Ratios follow the commercial spec.
    */
    'users' => (int) env('DEMO_USERS', 5000),
    'seed' => (int) env('DEMO_SEED', 20260923),
    'photos_per_user' => (float) env('DEMO_PHOTOS_PER_USER', 1.5),
    'premium_ratio' => (float) env('DEMO_PREMIUM_RATIO', 0.22),
    'vip_ratio' => (float) env('DEMO_VIP_RATIO', 0.07),
    'verified_ratio' => (float) env('DEMO_VERIFIED_RATIO', 0.32),
    'active_ratio' => (float) env('DEMO_ACTIVE_RATIO', 0.5),

    /*
    | Photo provider: "generated" renders abstract GD avatars locally
    | (safe to redistribute). "remote" downloads from a URL template
    | containing {seed}; the operator owns that source's licensing.
    */
    'photo_driver' => env('DEMO_PHOTO_DRIVER', 'generated'),
    'photo_url_template' => env('DEMO_PHOTO_URL_TEMPLATE', ''),
    'photo_retries' => (int) env('DEMO_PHOTO_RETRIES', 2),
    'photo_rate_limit_us' => (int) env('DEMO_PHOTO_RATE_LIMIT_US', 200000),
    'photo_disk' => env('DEMO_PHOTO_DISK', 'public'),
];
