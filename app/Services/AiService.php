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
        $perMin = (int) config('ai.rate_limits.per_user_per_minute', 10);
        $key = 'ai:rate:'.$user->id.':'.now()->format('YmdHi');
        $count = (int) Cache::get($key, 0);
        if ($count >= $perMin) {
            throw new \RuntimeException('AI rate limit exceeded. Try again in a minute.');
        }
        Cache::put($key, $count + 1, 65);
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
        $model = $options['model'] ?? config('ai.default_model', 'gpt-4o-mini');

        try {
            $result = $provider->chat($prompt, array_merge($options, ['model' => $model]));
            $this->costs->log($user, $provider->name(), $model, $purpose,
                $result['input_tokens'], $result['output_tokens'],
                $options['conversation_id'] ?? null, $options['message_id'] ?? null,
                $result['latency_ms'] ?? null, true);

            return $result + ['refused' => false];
        } catch (\Throwable $e) {
            $this->costs->log($user, $provider->name(), $model, $purpose, 0, 0,
                $options['conversation_id'] ?? null, null, null, false, $e->getMessage());
            throw $e;
        }
    }
}
