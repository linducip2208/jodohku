<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case Dismissed = 'dismissed';
    case Actioned = 'actioned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reviewing => 'Reviewing',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
            self::Dismissed => 'Dismissed',
            self::Actioned => 'Actioned',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Reviewing], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
