<?php

return [
    // Strict mode: custom domains only resolve after ownership verification.
    // Frictionless demo default: false (warn in admin, still resolve).
    'require_verification' => env('BRAND_REQUIRE_VERIFICATION', false),
];
