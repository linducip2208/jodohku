<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // site
            ['group' => 'site', 'key' => 'name', 'value' => 'Jodohku', 'type' => 'string'],
            ['group' => 'site', 'key' => 'tagline', 'value' => 'Temukan Jodohmu dengan Aman & Transparan', 'type' => 'string'],
            ['group' => 'site', 'key' => 'support_email', 'value' => 'support@jodohku.example.test', 'type' => 'string'],
            // registration
            ['group' => 'registration', 'key' => 'min_age', 'value' => '18', 'type' => 'integer'],
            ['group' => 'registration', 'key' => 'require_photo', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'registration', 'key' => 'require_questionnaire', 'value' => '0', 'type' => 'boolean'],
            // chat
            ['group' => 'chat', 'key' => 'messages_per_minute', 'value' => '20', 'type' => 'integer'],
            ['group' => 'chat', 'key' => 'messages_per_hour', 'value' => '200', 'type' => 'integer'],
            ['group' => 'chat', 'key' => 'max_length', 'value' => '2000', 'type' => 'integer'],
            ['group' => 'chat', 'key' => 'request_expiry_hours', 'value' => '72', 'type' => 'integer'],
            ['group' => 'chat', 'key' => 'free_messages_per_peer', 'value' => '1', 'type' => 'integer'],
            ['group' => 'chat', 'key' => 'premium_messages_per_peer', 'value' => '30', 'type' => 'integer'],
            // calls (voice & video both paid with tokens)
            ['group' => 'calls', 'key' => 'voice_per_minute', 'value' => '5', 'type' => 'integer'],
            ['group' => 'calls', 'key' => 'video_per_minute', 'value' => '10', 'type' => 'integer'],
            ['group' => 'calls', 'key' => 'invite_ttl_seconds', 'value' => '60', 'type' => 'integer'],
            // matching
            ['group' => 'matching', 'key' => 'daily_picks', 'value' => '10', 'type' => 'integer'],
            ['group' => 'matching', 'key' => 'min_daily_score', 'value' => '55', 'type' => 'integer'],
            ['group' => 'matching', 'key' => 'max_distance_km', 'value' => '200', 'type' => 'integer'],
            ['group' => 'matching', 'key' => 'weights', 'value' => json_encode(['age' => 10, 'location' => 10, 'preference' => 20, 'personality' => 20, 'interest' => 10, 'lifestyle' => 10, 'goal' => 10, 'behavior' => 10]), 'type' => 'json'],
            // ai
            ['group' => 'ai', 'key' => 'enabled', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'ai', 'key' => 'default_provider', 'value' => 'openai', 'type' => 'string'],
            ['group' => 'ai', 'key' => 'default_model', 'value' => 'gpt-4o-mini', 'type' => 'string'],
            ['group' => 'ai', 'key' => 'rate_per_minute', 'value' => '10', 'type' => 'integer'],
            ['group' => 'ai', 'key' => 'rate_per_day', 'value' => '200', 'type' => 'integer'],
            ['group' => 'ai', 'key' => 'monthly_budget_idr', 'value' => '2000000', 'type' => 'integer'],
            // moderation
            ['group' => 'moderation', 'key' => 'auto_block_risk', 'value' => '85', 'type' => 'integer'],
            ['group' => 'moderation', 'key' => 'auto_flag_risk', 'value' => '60', 'type' => 'integer'],
            ['group' => 'moderation', 'key' => 'ai_review_enabled', 'value' => '1', 'type' => 'boolean'],
            // payment
            ['group' => 'payment', 'key' => 'default_gateway', 'value' => 'midtrans', 'type' => 'string'],
            ['group' => 'payment', 'key' => 'currency', 'value' => 'IDR', 'type' => 'string'],
            ['group' => 'payment', 'key' => 'webhook_tolerance_seconds', 'value' => '300', 'type' => 'integer'],
            // storage
            ['group' => 'storage', 'key' => 'disk', 'value' => 'local', 'type' => 'string'],
            ['group' => 'storage', 'key' => 's3_compatible_endpoint', 'value' => '', 'type' => 'string'],
            ['group' => 'storage', 'key' => 'max_photo_mb', 'value' => '5', 'type' => 'integer'],
            // email
            ['group' => 'email', 'key' => 'from_address', 'value' => 'hello@jodohku.example.test', 'type' => 'string'],
            ['group' => 'email', 'key' => 'from_name', 'value' => 'Jodohku', 'type' => 'string'],
            // seo
            ['group' => 'seo', 'key' => 'site_name', 'value' => 'Jodohku', 'type' => 'string'],
            ['group' => 'seo', 'key' => 'default_title', 'value' => 'Jodohku — Temukan Jodohmu', 'type' => 'string'],
            ['group' => 'seo', 'key' => 'default_description', 'value' => 'Platform biro jodoh modern Indonesia: matchmaking, chat aman, verifikasi, virtual member transparan.', 'type' => 'string'],
            ['group' => 'seo', 'key' => 'robots_index', 'value' => '1', 'type' => 'boolean'],
        ];

        foreach ($rows as $r) {
            Setting::updateOrCreate(
                ['group' => $r['group'], 'key' => $r['key']],
                ['value' => $r['value'], 'type' => $r['type']]
            );
        }
        Setting::clearCache();
    }
}
