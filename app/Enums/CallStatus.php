<?php

namespace App\Enums;

enum CallStatus: string
{
    case Ringing = 'ringing';
    case Ongoing = 'ongoing';
    case Ended = 'ended';
    case Rejected = 'rejected';
    case Missed = 'missed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Ringing => 'Ringing',
            self::Ongoing => 'Ongoing',
            self::Ended => 'Ended',
            self::Rejected => 'Rejected',
            self::Missed => 'Missed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Ended, self::Rejected, self::Missed, self::Cancelled], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
