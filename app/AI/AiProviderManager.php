<?php

namespace App\AI;

class AiProviderManager
{
    /** @var array<string, AiProviderInterface> */
    protected array $drivers = [];

    public function driver(?string $name = null): AiProviderInterface
    {
        $name ??= (string) config('ai.default_provider', 'openai');
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }
        $cfg = config('ai.providers.'.$name);
        if (! $cfg || empty($cfg['driver'])) {
            throw new \RuntimeException("AI provider [{$name}] not configured.");
        }
        $instance = app($cfg['driver']);
        if (! $instance instanceof AiProviderInterface) {
            throw new \RuntimeException('AI driver must implement AiProviderInterface.');
        }
        $this->drivers[$name] = $instance;

        return $instance;
    }

    public function extend(string $name, AiProviderInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }
}
