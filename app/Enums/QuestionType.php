<?php

namespace App\Enums;

enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Scale = 'scale';
    case Text = 'text';
    case Boolean = 'boolean';
    case Number = 'number';
    case Date = 'date';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => 'Single Choice',
            self::MultipleChoice => 'Multiple Choice',
            self::Scale => 'Scale',
            self::Text => 'Text',
            self::Boolean => 'Yes / No',
            self::Number => 'Number',
            self::Date => 'Date',
        };
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultipleChoice], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
