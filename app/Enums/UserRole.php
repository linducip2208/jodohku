<?php

namespace App\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Premium = 'premium';
    case Operator = 'operator';
    case Moderator = 'moderator';
    case Admin = 'admin';
    case Superadmin = 'superadmin';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::Premium => 'Premium',
            self::Operator => 'Operator',
            self::Moderator => 'Moderator',
            self::Admin => 'Admin',
            self::Superadmin => 'Superadmin',
        };
    }

    public function isStaff(): bool
    {
        return in_array($this, [self::Operator, self::Moderator, self::Admin, self::Superadmin], true);
    }

    public function rank(): int
    {
        return match ($this) {
            self::Member => 10,
            self::Premium => 20,
            self::Operator => 40,
            self::Moderator => 60,
            self::Admin => 80,
            self::Superadmin => 100,
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
