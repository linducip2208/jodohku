<?php

namespace App\AI;

interface AiProviderInterface
{
    public function name(): string;

    /** @param array{model?:string, temperature?:float, max_tokens?:int, system?:string} $options */
    public function chat(string $prompt, array $options = []): array;

    /** @return float[]|null */
    public function embeddings(string $text): ?array;
}
