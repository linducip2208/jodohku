<?php

namespace App\AI;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Models\User;

class AiCostService
{
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
