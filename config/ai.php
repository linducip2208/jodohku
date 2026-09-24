<?php

use App\AI\CompatibleAiProvider;
use App\AI\OpenAiProvider;

return [
    'enabled' => env('AI_ENABLED', true),
    'default_provider' => env('AI_PROVIDER', 'openai'),
    'default_model' => env('AI_MODEL', 'gpt-4o-mini'),

    'providers' => [
        'openai' => [
            'driver' => OpenAiProvider::class,
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY', ''),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'timeout' => 45,
        ],
        'compatible' => [
            'driver' => CompatibleAiProvider::class,
            'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('AI_API_KEY', ''),
            'model' => env('AI_MODEL', 'gpt-4o-mini'),
            'timeout' => 45,
        ],
    ],

    'models' => [
        'gpt-4o-mini' => ['cost_per_1k_input' => 0.00015, 'cost_per_1k_output' => 0.0006, 'max_tokens' => 128000],
    ],

    'rate_limits' => [
        'per_user_per_minute' => env('AI_RATE_PER_MIN', 10),
        'per_user_per_day' => env('AI_RATE_PER_DAY', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Global spend cap + kill switch (SCALE-AUDIT P0)
    |--------------------------------------------------------------------------
    | Per-user quotas stop abuse by one member; they do NOT stop a global
    | cost blowout (bug loop, virtual-member storm). The monthly cap is
    | enforced in AiService before any provider call; the kill switch
    | disables all AI instantly via env without a deploy.
    */
    'spending' => [
        'monthly_cap_usd' => (float) env('AI_MONTHLY_CAP_USD', 50),
        'kill_switch' => env('AI_KILL_SWITCH', false),
        // Spend cache TTL (seconds): caps are enforced on cached totals so
        // every chat() doesn't run a SUM over ai_usage_logs.
        'cache_ttl' => (int) env('AI_SPEND_CACHE_TTL', 300),
    ],

    'guardrails' => [
        'refuse_secrets' => true,
        'grounded_only' => true,
        'max_profiles_per_answer' => 10,
    ],
];
