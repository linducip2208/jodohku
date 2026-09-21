<?php

namespace App\Enums;

enum AiMode: string
{
    case Template = 'template';
    case Hybrid = 'hybrid';
    case Ai = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::Template => 'Template',
            self::Hybrid => 'Hybrid',
            self::Ai => 'Full AI',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
