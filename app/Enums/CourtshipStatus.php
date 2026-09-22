<?php

namespace App\Enums;

enum CourtshipStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Active;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
