<?php

namespace App\AI;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Models\User;

class AiCostService
{
    /** Global kill switch: env AI_KILL_SWITCH=true disables all AI instantly. */
    public function isKilled(): bool
    {
        return filter_var(config('ai.spending.kill_switch', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** Total USD spent since the start of the current month (cached 5 min). */
    public function monthlySpend(): float
    {
        $ttl = max(60, (int) config('ai.spending.cache_ttl', 300));
        $key = 'ai:spend:'.now()->format('Ym');

        try {
            return (float) \Illuminate\Support\Facades\Cache::remember($key, $ttl, fn () => (float) AiUsageLog::where('created_at', '>=', now()->startOfMonth())->sum('cost'));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /** True when a positive monthly cap is configured and already reached. */
    public function isOverMonthlyCap(): bool
    {
        $cap = (float) config('ai.spending.monthly_cap_usd', 0);
        if ($cap <= 0) {
            return false;
        }

        return $this->monthlySpend() >= $cap;
    }
    public function log(?User $user, string $providerCode, string $modelCode, string $purpose, int $inputTokens, int $outputTokens, ?int $conversationId = null, ?int $messageId = null, ?int $latencyMs = null, bool $success = true, ?string $error = null): AiUsageLog
    {
        $provider = AiProvider::where('code', $providerCode)->first();
        $model = AiModel::where('code', $modelCode)->first();
        $cost = 0.0;
        if ($model) {
            $cost = $model->estimateCost($inputTokens, $outputTokens);
        } else {
            $cfg = config('ai.models.'.$modelCode);
            if ($cfg) {
                $cost = ($inputTokens / 1000) * (float) ($cfg['cost_per_1k_input'] ?? 0)
                    + ($outputTokens / 1000) * (float) ($cfg['cost_per_1k_output'] ?? 0);
            }
        }

        return AiUsageLog::create([
            'ai_provider_id' => $provider?->id,
            'ai_model_id' => $model?->id,
            'user_id' => $user?->id,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'purpose' => $purpose,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $cost,
            'latency_ms' => $latencyMs,
            'is_success' => $success,
            'error_message' => $error ? substr($error, 0, 1000) : null,
        ]);
    }

    public function totalCostForUser(int $userId, ?string $since = null): float
    {
        return (float) AiUsageLog::where('user_id', $userId)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->sum('cost');
    }
}
