<?php

namespace App\Enums;

enum CreditTxnType: string
{
    case Purchase = 'purchase';
    case Spend = 'spend';
    case Earn = 'earn';
    case Bonus = 'bonus';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Expire = 'expire';
    case Gift = 'gift';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Spend => 'Spend',
            self::Earn => 'Earn',
            self::Bonus => 'Bonus',
            self::Refund => 'Refund',
            self::Adjustment => 'Adjustment',
            self::Expire => 'Expire',
            self::Gift => 'Gift',
        };
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::Purchase, self::Earn, self::Bonus, self::Refund, self::Gift], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
