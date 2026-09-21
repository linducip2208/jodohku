<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Deleted = 'deleted';
    case Moderated = 'moderated';

    public function label(): string
    {
        return match ($this) {
            self::Sending => 'Sending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
            self::Failed => 'Failed',
            self::Deleted => 'Deleted',
            self::Moderated => 'Moderated',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Read, self::Failed, self::Deleted, self::Moderated], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
