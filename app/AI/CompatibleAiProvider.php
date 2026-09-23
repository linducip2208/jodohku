<?php

namespace App\AI;

use Illuminate\Support\Facades\Http;

class CompatibleAiProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'compatible';
    }

    public function chat(string $prompt, array $options = []): array
    {
        $base = rtrim((string) config('ai.providers.compatible.base_url', 'https://api.openai.com/v1'), '/');
        $key = (string) config('ai.providers.compatible.api_key', '');
        $model = $options['model'] ?? config('ai.providers.compatible.model', config('ai.default_model', 'gpt-4o-mini'));
        $started = microtime(true);
        $res = Http::timeout((int) config('ai.providers.compatible.timeout', 45))
            ->withHeaders($key ? ['Authorization' => 'Bearer '.$key] : [])
            ->post($base.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $options['system'] ?? 'You are a helpful dating assistant for '.config('app.name').' (Indonesia).'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 500,
            ]);
        $latency = (int) ((microtime(true) - $started) * 1000);
        if (! $res->successful()) {
            throw new \RuntimeException('AI provider error: '.$res->body());
        }
        $json = $res->json();
        $text = $json['choices'][0]['message']['content'] ?? '';

        return [
            'text' => trim((string) $text),
            'input_tokens' => (int) ($json['usage']['prompt_tokens'] ?? max(1, (int) ceil(mb_strlen($prompt) / 4))),
            'output_tokens' => (int) ($json['usage']['completion_tokens'] ?? max(1, (int) ceil(mb_strlen((string) $text) / 4))),
            'model' => $model,
            'latency_ms' => $latency,
        ];
    }

    public function embeddings(string $text): ?array
    {
        return null;
    }
}
