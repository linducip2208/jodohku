<?php

namespace App\Enums;

enum PrivacyVisibility: string
{
    case Public = 'public';
    case MembersOnly = 'members_only';
    case PremiumOnly = 'premium_only';
    case MatchesOnly = 'matches_only';
    case Private = 'private';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::MembersOnly => 'Members Only',
            self::PremiumOnly => 'Premium Only',
            self::MatchesOnly => 'Matches Only',
            self::Private => 'Private',
            self::Hidden => 'Hidden',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
