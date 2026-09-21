<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
    case Married = 'married';
    case Separated = 'separated';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Divorced => 'Divorced',
            self::Widowed => 'Widowed',
            self::Married => 'Married',
            self::Separated => 'Separated',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
