<?php

namespace App\Enums;

enum QuestionCategory: string
{
    case Personality = 'personality';
    case Lifestyle = 'lifestyle';
    case Values = 'values';
    case Interests = 'interests';
    case Relationship = 'relationship';
    case Background = 'background';
    case Preferences = 'preferences';
    case Compatibility = 'compatibility';

    public function label(): string
    {
        return match ($this) {
            self::Personality => 'Personality',
            self::Lifestyle => 'Lifestyle',
            self::Values => 'Values',
            self::Interests => 'Interests',
            self::Relationship => 'Relationship',
            self::Background => 'Background',
            self::Preferences => 'Preferences',
            self::Compatibility => 'Compatibility',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
