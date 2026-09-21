<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case FakeProfile = 'fake_profile';
    case Harassment = 'harassment';
    case InappropriateContent = 'inappropriate_content';
    case Nudity = 'nudity';
    case Scam = 'scam';
    case Violence = 'violence';
    case HateSpeech = 'hate_speech';
    case Underage = 'underage';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam',
            self::FakeProfile => 'Fake Profile',
            self::Harassment => 'Harassment',
            self::InappropriateContent => 'Inappropriate Content',
            self::Nudity => 'Nudity',
            self::Scam => 'Scam / Fraud',
            self::Violence => 'Violence',
            self::HateSpeech => 'Hate Speech',
            self::Underage => 'Underage User',
            self::Other => 'Other',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
