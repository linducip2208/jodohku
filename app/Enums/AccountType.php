<?php

namespace App\Enums;

enum AccountType: string
{
    case Real = 'real';
    case Virtual = 'virtual';
    case Ai = 'ai';
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Real => 'Real User',
            self::Virtual => 'Virtual Profile',
            self::Ai => 'AI Persona',
            self::Admin => 'Administrator',
            self::Moderator => 'Moderator',
            self::Operator => 'Operator',
        };
    }

    public function isHuman(): bool
    {
        return $this === self::Real;
    }

    public function isStaff(): bool
    {
        return in_array($this, [self::Admin, self::Moderator, self::Operator], true);
    }

    public function isSynthetic(): bool
    {
        return in_array($this, [self::Virtual, self::Ai], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
