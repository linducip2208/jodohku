<?php

namespace App\AI;

use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    public function name(): string { return 'openai'; }

    protected function base(): string
    {
        return rtrim((string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1'), '/');
    }

    protected function key(): string
    {
        return (string) config('ai.providers.openai.api_key', '');
    }

    public function chat(string $prompt, array $options = []): array
    {
        $model = $options['model'] ?? config('ai.providers.openai.model', 'gpt-4o-mini');
        $started = microtime(true);
        $res = Http::timeout((int) config('ai.providers.openai.timeout', 45))
            ->withToken($this->key())
            ->post($this->base().'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $options['system'] ?? 'You are a helpful dating assistant for Jodohku (Indonesia). Reply in Indonesian unless asked otherwise.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 500,
            ]);
        $latency = (int) ((microtime(true) - $started) * 1000);
        if (! $res->successful()) {
            throw new \RuntimeException('OpenAI error: '.$res->body());
        }
        $json = $res->json();
        $text = $json['choices'][0]['message']['content'] ?? '';

        return [
            'text' => trim((string) $text),
            'input_tokens' => (int) ($json['usage']['prompt_tokens'] ?? $this->estimateTokens($prompt)),
            'output_tokens' => (int) ($json['usage']['completion_tokens'] ?? $this->estimateTokens((string) $text)),
            'model' => $model,
            'latency_ms' => $latency,
        ];
    }

    public function embeddings(string $text): ?array
    {
        $res = Http::timeout(30)->withToken($this->key())
            ->post($this->base().'/embeddings', [
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ]);
        if (! $res->successful()) {
            return null;
        }

        return $res->json('data.0.embedding');
    }

    protected function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }
}
