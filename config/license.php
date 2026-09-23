<?php

return [
    /*
    | Commercial license gate. DISABLED by default so development, testing,
    | and self-hosted installs work out of the box. Enable only for
    | distributed/white-label builds: LICENSE_ENABLED=true + key below.
    | No vendor URLs or secrets are hardcoded; wire your own activation
    | endpoint via LICENSE_VERIFY_URL when you operate one.
    */
    'enabled' => env('LICENSE_ENABLED', false),
    'key' => env('LICENSE_KEY', ''),
    'domain' => env('LICENSE_DOMAIN', ''),
    'expires_at' => env('LICENSE_EXPIRES_AT', ''),
    'support_until' => env('LICENSE_SUPPORT_UNTIL', ''),
    'verify_url' => env('LICENSE_VERIFY_URL', ''),
    'verify_timeout' => (int) env('LICENSE_VERIFY_TIMEOUT', 10),
];
