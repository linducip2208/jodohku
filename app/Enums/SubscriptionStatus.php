<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Incomplete = 'incomplete';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Trialing => 'Trialing',
            self::Active => 'Active',
            self::PastDue => 'Past Due',
            self::Paused => 'Paused',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Incomplete => 'Incomplete',
        };
    }

    public function grantsPremium(): bool
    {
        return in_array($this, [self::Active, self::Trialing], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
