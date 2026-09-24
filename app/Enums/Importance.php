<?php

namespace App\Enums;

enum Importance: string
{
    case Required = 'required';
    case VeryImportant = 'very_important';
    case Important = 'important';
    case Preferred = 'preferred';
    case Neutral = 'neutral';
    case Avoid = 'avoid';

    public function label(): string
    {
        return match ($this) {
            self::Required => 'Required',
            self::VeryImportant => 'Very important',
            self::Important => 'Important',
            self::Preferred => 'Preferred',
            self::Neutral => 'Neutral',
            self::Avoid => 'Avoid',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Required => 100,
            self::VeryImportant => 80,
            self::Important => 60,
            self::Preferred => 30,
            self::Neutral => 10,
            self::Avoid => -50,
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
