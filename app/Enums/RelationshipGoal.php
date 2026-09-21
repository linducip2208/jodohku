<?php

namespace App\Enums;

enum RelationshipGoal: string
{
    case Marriage = 'marriage';
    case SeriousRelationship = 'serious_relationship';
    case Dating = 'dating';
    case Friendship = 'friendship';
    case Casual = 'casual';
    case Networking = 'networking';
    case Undecided = 'undecided';

    public function label(): string
    {
        return match ($this) {
            self::Marriage => 'Marriage',
            self::SeriousRelationship => 'Serious Relationship',
            self::Dating => 'Dating',
            self::Friendship => 'Friendship',
            self::Casual => 'Casual',
            self::Networking => 'Networking',
            self::Undecided => 'Undecided',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
