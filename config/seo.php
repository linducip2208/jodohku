<?php

return [
    /*
    | Central SEO/GEO/PSEO configuration. Every key is white-label
    | friendly: override via environment, or per-install via the Settings
    | `seo` group (superadmin UI), which takes precedence over these
    | defaults. See SEO.md / GEO.md / PSEO.md.
    */
    'site_name' => env('SEO_SITE_NAME', 'Jodohku'),
    'title' => env('SEO_TITLE', env('SEO_DEFAULT_TITLE', 'Jodohku — Biro Jodoh Modern Indonesia')),
    'description' => env('SEO_DESCRIPTION', env('SEO_DEFAULT_DESCRIPTION', 'Biro jodoh modern Indonesia: matchmaking serius, Smart Taaruf, chat aman, konselor, dan komunitas.')),
    'keywords' => env('SEO_KEYWORDS', 'biro jodoh, taaruf, matchmaking indonesia, dating serius, cari jodoh'),
    'default_image' => env('SEO_DEFAULT_IMAGE', '/og-cover.jpg'),
    'logo' => env('SEO_LOGO', '/favicon.ico'),
    'twitter_handle' => env('SEO_TWITTER_HANDLE', ''),
    'locale' => env('SEO_LOCALE', 'id_ID'),
    'robots_index' => env('SEO_ROBOTS', true),
    'sitemap_enabled' => env('SEO_SITEMAP_ENABLED', true),
    'pseo_enabled' => env('SEO_PSEO_ENABLED', true),
    'geo_enabled' => env('SEO_GEO_ENABLED', true),
    'schema_enabled' => env('SEO_SCHEMA_ENABLED', true),
    'pseo_min_members' => (int) env('SEO_PSEO_MIN_MEMBERS', 10),
    // Verification / analytics IDs (empty = disabled; never hardcoded).
    'google_site_verification' => env('SEO_GOOGLE_VERIFICATION', ''),
    'bing_site_verification' => env('SEO_BING_VERIFICATION', ''),
    'analytics_id' => env('SEO_ANALYTICS_ID', ''),
];
