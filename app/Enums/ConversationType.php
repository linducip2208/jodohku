<?php

namespace App\Enums;

enum ConversationType: string
{
    case Direct = 'direct';
    case Group = 'group';
    case Support = 'support';
    case Ai = 'ai';
    case Virtual = 'virtual';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct',
            self::Group => 'Group',
            self::Support => 'Support',
            self::Ai => 'AI Chat',
            self::Virtual => 'Virtual Companion',
            self::Operator => 'Operator Assisted',
        };
    }

    public function isAutomated(): bool
    {
        return in_array($this, [self::Ai, self::Virtual, self::Operator], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
