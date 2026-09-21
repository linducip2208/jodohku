<?php

namespace Database\Seeders;

use App\Enums\ModerationAction;
use App\Models\ModerationRule;
use App\Models\ProfanityCategory;
use App\Models\ProfanityWord;
use Illuminate\Database\Seeder;

class ProfanitySeeder extends Seeder
{
    public function run(): void
    {
        $cats = [
            ['name' => 'Kasar Indonesia', 'slug' => 'kasar-id', 'description' => 'Umpatan Bahasa Indonesia.', 'severity' => 3],
            ['name' => 'Profanity English', 'slug' => 'profanity-en', 'description' => 'English profanity.', 'severity' => 3],
            ['name' => 'Seksual / Vulgar', 'slug' => 'sexual', 'description' => 'Konten seksual eksplisit.', 'severity' => 4],
            ['name' => 'SARA / Hate', 'slug' => 'hate', 'description' => 'Ujaran kebencian SARA.', 'severity' => 5],
        ];
        $catIds = [];
        foreach ($cats as $c) {
            $row = ProfanityCategory::firstOrCreate(['slug' => $c['slug']], $c);
            $catIds[$c['slug']] = $row->id;
        }

        // Fictional moderation wordlist (real words needed for the filter to work).
        $words = [
            // Indonesian
            ['w' => 'anjing', 'lang' => 'id', 'cat' => 'kasar-id', 'sev' => 3],
            ['w' => 'bangsat', 'lang' => 'id', 'cat' => 'kasar-id', 'sev' => 4],
            ['w' => 'bajingan', 'lang' => 'id', 'cat' => 'kasar-id', 'sev' => 3],
            ['w' => 'tolol', 'lang' => 'id', 'cat' => 'kasar-id', 'sev' => 2],
            ['w' => 'goblok', 'lang' => 'id', 'cat' => 'kasar-id', 'sev' => 3],
            ['w' => 'kontol', 'lang' => 'id', 'cat' => 'sexual', 'sev' => 5],
            ['w' => 'memek', 'lang' => 'id', 'cat' => 'sexual', 'sev' => 5],
            ['w' => 'ngentot', 'lang' => 'id', 'cat' => 'sexual', 'sev' => 5],
            // English
            ['w' => 'fuck', 'lang' => 'en', 'cat' => 'profanity-en', 'sev' => 4],
            ['w' => 'shit', 'lang' => 'en', 'cat' => 'profanity-en', 'sev' => 3],
            ['w' => 'bitch', 'lang' => 'en', 'cat' => 'profanity-en', 'sev' => 3],
            ['w' => 'bastard', 'lang' => 'en', 'cat' => 'profanity-en', 'sev' => 3],
            // Phone-number exfiltration is handled by ScamDetectionService (regex),
            // but a regex profanity entry guards masked variants like 08xx-xxx.
            ['w' => '/0\s*8\s*\d[\d\s\-]{7,}/', 'lang' => 'id', 'cat' => 'kasar-id', 'sev' => 2, 'regex' => true],
        ];

        foreach ($words as $e) {
            ProfanityWord::firstOrCreate(
                ['word' => $e['w']],
                [
                    'profanity_category_id' => $catIds[$e['cat']],
                    'replacement' => str_repeat('*', min(8, mb_strlen($e['w']))),
                    'language' => $e['lang'],
                    'severity' => $e['sev'],
                    'is_regex' => $e['regex'] ?? false,
                    'is_active' => true,
                ]
            );
        }

        $rules = [
            ['name' => 'Phone number sharing', 'description' => 'Nomor HP / WA di chat awal wajib diflag.', 'trigger_type' => 'pattern', 'trigger_value' => 'phone_number', 'action' => ModerationAction::Warn, 'threshold' => 1, 'window_minutes' => 60, 'priority' => 90],
            ['name' => 'Off-platform invite', 'description' => 'Ajakan pindah ke Telegram/WA/IG.', 'trigger_type' => 'pattern', 'trigger_value' => 'offplatform_invite', 'action' => ModerationAction::Warn, 'threshold' => 1, 'window_minutes' => 60, 'priority' => 80],
            ['name' => 'Profanity burst', 'description' => '3+ kata kasar dalam 10 menit = mute.', 'trigger_type' => 'keyword', 'trigger_value' => 'profanity', 'action' => ModerationAction::Mute, 'threshold' => 3, 'window_minutes' => 10, 'priority' => 70],
            ['name' => 'Credential phishing', 'description' => 'Minta OTP/password = eskalasi.', 'trigger_type' => 'pattern', 'trigger_value' => 'credential_phish', 'action' => ModerationAction::Escalate, 'threshold' => 1, 'window_minutes' => 1440, 'priority' => 100],
            ['name' => 'Investment lure', 'description' => 'Tawaran investasi/crypto = eskalasi.', 'trigger_type' => 'pattern', 'trigger_value' => 'investment_lure', 'action' => ModerationAction::Escalate, 'threshold' => 1, 'window_minutes' => 1440, 'priority' => 95],
        ];
        foreach ($rules as $r) {
            ModerationRule::firstOrCreate(['name' => $r['name']], $r + ['is_active' => true]);
        }
    }
}
