<?php

namespace App\Enums;

enum CourtshipStage: string
{
    case Kenalan = 'kenalan';
    case Taaruf = 'taaruf';
    case Khitbah = 'khitbah';
    case Menikah = 'menikah';

    public function label(): string
    {
        return match ($this) {
            self::Kenalan => 'Kenalan',
            self::Taaruf => 'Taaruf',
            self::Khitbah => 'Khitbah',
            self::Menikah => 'Menikah',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Kenalan => self::Taaruf,
            self::Taaruf => self::Khitbah,
            self::Khitbah => self::Menikah,
            self::Menikah => null,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Menikah;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
