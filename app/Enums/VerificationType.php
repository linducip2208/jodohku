<?php

namespace App\Enums;

enum VerificationType: string
{
    case IdCard = 'id_card';
    case Selfie = 'selfie';
    case Video = 'video';
    case Phone = 'phone';
    case Email = 'email';
    case Income = 'income';
    case Education = 'education';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::IdCard => 'ID Card',
            self::Selfie => 'Selfie',
            self::Video => 'Video',
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::Income => 'Income',
            self::Education => 'Education',
            self::Manual => 'Manual Review',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
