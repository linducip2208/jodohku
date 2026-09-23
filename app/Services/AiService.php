<?php

namespace App\Services;

use App\AI\AiCostService;
use App\AI\AiProviderManager;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AiService
{
    public function __construct(
        protected AiProviderManager $providers,
        protected AiCostService $costs,
    ) {}

    protected function guardrails(string $prompt): ?string
    {
        $lower = mb_strtolower($prompt);
        foreach (['password', 'otp', 'kode otp', 'pin atm', 'cvv', 'nomor kartu', 'api key', 'secret key', 'private key'] as $secret) {
            if (str_contains($lower, $secret) && (str_contains($lower, 'berikan') || str_contains($lower, 'kasih') || str_contains($lower, 'give') || str_contains($lower, 'show'))) {
                return 'Maaf, saya tidak bisa memberikan atau meminta password, OTP, atau data rahasia apa pun. Jangan pernah membagikan kode rahasia Anda kepada siapa pun.';
            }
        }

        return null;
    }

    protected function checkRate(?User $user): void
    {
        if (! $user) {
            return;
        }
        $perMin = max(1, (int) config('ai.rate_limits.per_user_per_minute', 10));
        $perDay = max(1, (int) config('ai.rate_limits.per_user_per_day', 200));
        // Premium members get 3x daily budget; free members use base config.
        try {
            if ($user->isPremium()) {
                $perDay *= 3;
            }
        } catch (\Throwable) {
        }
        $minKey = 'ai:rate:min:'.$user->id.':'.now()->format('YmdHi');
        $dayKey = 'ai:rate:day:'.$user->id.':'.now()->format('Ymd');
        // Atomic increments with expiry set only on first hit (no reset race).
        $minCount = (int) Cache::increment($minKey);
        if ($minCount === 1) {
            Cache::put($minKey, 1, 70);
        }
        $dayCount = (int) Cache::increment($dayKey);
        if ($dayCount === 1) {
            Cache::put($dayKey, 1, 86400 + 300);
        }
        if ($minCount > $perMin) {
            throw new \RuntimeException('AI rate limit exceeded. Try again in a minute.');
        }
        if ($dayCount > $perDay) {
            throw new \RuntimeException('Daily AI quota reached. Try again tomorrow or upgrade to Premium.');
        }
    }

    /** Server-side allowlist: clients cannot request arbitrary models. */
    protected function resolveModel(array $options): string
    {
        $requested = (string) ($options['model'] ?? config('ai.default_model', 'gpt-4o-mini'));
        $allowed = array_keys((array) config('ai.models', []));
        // Always allow configured provider defaults even if not in cost map.
        $allowed[] = (string) config('ai.default_model', 'gpt-4o-mini');
        foreach ((array) config('ai.providers', []) as $provider) {
            if (! empty($provider['model'])) {
                $allowed[] = (string) $provider['model'];
            }
        }
        $allowed = array_values(array_unique($allowed));
        if (! in_array($requested, $allowed, true)) {
            return (string) config('ai.default_model', 'gpt-4o-mini');
        }

        return $requested;
    }

    /** Per-purpose output cap enforced server-side (prevents token abuse). */
    protected function maxTokensFor(string $purpose, array $options): int
    {
        $caps = [
            'translate' => 400, 'rewrite' => 200, 'suggested_replies' => 200,
            'icebreaker' => 150, 'catch_up' => 120, 'digest' => 100,
            'taaruf_topics' => 300, 'matchmaker' => 400, 'chat' => 500,
        ];
        $cap = $caps[$purpose] ?? 500;
        $asked = (int) ($options['max_tokens'] ?? $cap);

        return max(32, min($cap, $asked));
    }

    /** Grounded chat completion with guardrails + cost logging. */
    public function chat(string $prompt, array $options = [], ?User $user = null, string $purpose = 'chat'): array
    {
        if (! config('jodohku.features.ai', true)) {
            throw new \RuntimeException('AI feature disabled.');
        }
        if ($refusal = $this->guardrails($prompt)) {
            return ['text' => $refusal, 'refused' => true, 'input_tokens' => 0, 'output_tokens' => 0, 'cost' => 0];
        }
        $this->checkRate($user);

        $provider = $this->providers->driver($options['provider'] ?? null);
        $model = $this->resolveModel($options);
        $options['max_tokens'] = $this->maxTokensFor($purpose, $options);
        $conversationId = $options['conversation_id'] ?? null;
        $messageId = $options['message_id'] ?? null;
        // Never forward raw PII-heavy option bags to providers; strip internal ids.
        unset($options['conversation_id'], $options['message_id']);

        try {
            $result = $provider->chat($prompt, array_merge($options, ['model' => $model]));
            $this->costs->log($user, $provider->name(), $model, $purpose,
                $result['input_tokens'] ?? 0, $result['output_tokens'] ?? 0,
                $conversationId, $messageId,
                $result['latency_ms'] ?? null, true);

            return $result + ['refused' => false];
        } catch (\Throwable $e) {
            $this->costs->log($user, $provider->name(), $model, $purpose, 0, 0,
                $conversationId, null, null, false, $e->getMessage());
            throw $e;
        }
    }
}
