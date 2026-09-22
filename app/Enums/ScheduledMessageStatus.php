<?php

namespace App\Enums;

enum ScheduledMessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
