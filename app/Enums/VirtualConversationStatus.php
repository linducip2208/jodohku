<?php

namespace App\Enums;

enum VirtualConversationStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Transferred = 'transferred';
    case Closed = 'closed';
    case Escalated = 'escalated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Transferred => 'Transferred to Human',
            self::Closed => 'Closed',
            self::Escalated => 'Escalated',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Active, self::Paused, self::Escalated], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
