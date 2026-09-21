<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Banned = 'banned';
    case PendingVerification = 'pending_verification';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
            self::Banned => 'Banned',
            self::PendingVerification => 'Pending Verification',
            self::Deleted => 'Deleted',
        };
    }

    public function canLogin(): bool
    {
        return $this === self::Active;
    }

    public function isRestricted(): bool
    {
        return in_array($this, [self::Suspended, self::Banned, self::Deleted], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
