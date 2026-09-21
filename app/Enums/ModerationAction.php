<?php

namespace App\Enums;

enum ModerationAction: string
{
    case None = 'none';
    case Warn = 'warn';
    case Mute = 'mute';
    case ShadowBan = 'shadow_ban';
    case Suspend = 'suspend';
    case Ban = 'ban';
    case DeleteContent = 'delete_content';
    case Approve = 'approve';
    case Reject = 'reject';
    case Escalate = 'escalate';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No Action',
            self::Warn => 'Warn',
            self::Mute => 'Mute',
            self::ShadowBan => 'Shadow Ban',
            self::Suspend => 'Suspend',
            self::Ban => 'Ban',
            self::DeleteContent => 'Delete Content',
            self::Approve => 'Approve',
            self::Reject => 'Reject',
            self::Escalate => 'Escalate',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
