<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\AiMode;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiPersonality;
use App\Models\Profile;
use App\Models\ProfilePhoto;
use App\Models\User;
use App\Models\VirtualProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class VirtualMemberSeeder extends Seeder
{
    public function run(): void
    {
        $personalities = [
            ['code' => 'ceria', 'name' => 'Ceria & Ramah', 'description' => 'Hangat, suportif, humor ringan.', 'system_prompt' => 'Kamu persona virtual Jodohku yang ceria, ramah, Bahasa Indonesia santai, maks 40 kata. Selalu transparan bahwa kamu profil virtual.', 'tone' => 'friendly', 'language' => 'id'],
            ['code' => 'bijak', 'name' => 'Bijak & Tenang', 'description' => 'Dewasa, pendengar baik.', 'system_prompt' => 'Kamu persona virtual Jodohku yang bijak dan tenang. Bahasa Indonesia sopan, maks 40 kata. Ingatkan bahwa kamu profil virtual.', 'tone' => 'calm', 'language' => 'id'],
            ['code' => 'humoris', 'name' => 'Humoris', 'description' => 'Santai dan suka bercanda ringan.', 'system_prompt' => 'Kamu persona virtual Jodohku yang humoris tapi sopan. Maks 40 kata, Bahasa Indonesia. Transparan sebagai profil virtual.', 'tone' => 'playful', 'language' => 'id'],
        ];
        $personalityIds = [];
        foreach ($personalities as $p) {
            $row = AiPersonality::firstOrCreate(['code' => $p['code']], $p + ['is_active' => true]);
            $personalityIds[$p['code']] = $row->id;
        }

        // Explicitly fictional virtual/AI personas.
        $virtuals = [
            ['name' => 'Sinta Virtual', 'email' => 'sinta.virtual@example.test', 'type' => AccountType::Virtual, 'gender' => Gender::Female, 'city' => 'Jakarta', 'mode' => AiMode::Template, 'personality' => 'ceria'],
            ['name' => 'Raka Virtual', 'email' => 'raka.virtual@example.test', 'type' => AccountType::Virtual, 'gender' => Gender::Male, 'city' => 'Bandung', 'mode' => AiMode::Hybrid, 'personality' => 'humoris'],
            ['name' => 'Nadia AI', 'email' => 'nadia.ai@example.test', 'type' => AccountType::Ai, 'gender' => Gender::Female, 'city' => 'Surabaya', 'mode' => AiMode::Ai, 'personality' => 'bijak'],
            ['name' => 'Bagas AI', 'email' => 'bagas.ai@example.test', 'type' => AccountType::Ai, 'gender' => Gender::Male, 'city' => 'Semarang', 'mode' => AiMode::Template, 'personality' => 'ceria'],
            ['name' => 'Laras Virtual', 'email' => 'laras.virtual@example.test', 'type' => AccountType::Virtual, 'gender' => Gender::Female, 'city' => 'Medan', 'mode' => AiMode::Hybrid, 'personality' => 'bijak'],
            ['name' => 'Dimas AI', 'email' => 'dimas.ai@example.test', 'type' => AccountType::Ai, 'gender' => Gender::Male, 'city' => 'Jakarta', 'mode' => AiMode::Ai, 'personality' => 'humoris'],
        ];

        foreach ($virtuals as $v) {
            $user = User::firstOrCreate(
                ['email' => $v['email']],
                [
                    'name' => $v['name'].' (Virtual — dikelola Jodohku)',
                    'display_name' => explode(' ', $v['name'])[0],
                    'password' => Hash::make('virtual-'.uniqid()),
                    'account_type' => $v['type'],
                    'role' => UserRole::Member,
                    'status' => UserStatus::Active,
                    'date_of_birth' => '1996-06-15',
                    'gender' => $v['gender'],
                    'city' => $v['city'],
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'is_verified' => false,
                    'profile_completion' => 90,
                ]
            );

            Profile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'headline' => 'Profil Virtual Jodohku — transparan & aman.',
                    'bio' => 'Halo! Aku '.$v['name'].', profil virtual/AI Jodohku untuk menemani latihan ngobrol. '
                        .'Aku BUKAN orang sungguhan — dikelola Jodohku/operator. Cocok untuk pemanasan sebelum kenalan dengan member asli.',
                    'occupation' => 'Virtual Companion',
                    'education' => '—',
                    'relationship_goal' => 'friendship',
                    'is_complete' => true,
                ]
            );

            ProfilePhoto::firstOrCreate(
                ['user_id' => $user->id, 'path' => 'placeholders/virtual/'.strtolower(str_replace(' ', '-', $v['name'])).'.jpg'],
                ['sort_order' => 0, 'is_primary' => true, 'is_approved' => true]
            );

            VirtualProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'ai_personality_id' => $personalityIds[$v['personality']],
                    'mode' => $v['mode'],
                    'persona_prompt' => 'Persona '.$v['name'].' ('.$v['mode']->value.') — ramah, transparan, Bahasa Indonesia.',
                    'greeting_message' => 'Halo! Aku '.$v['name'].' 👋 (profil virtual Jodohku — bukan orang sungguhan). Lagi sibuk apa hari ini?',
                    'reply_templates' => [
                        'Hai! Senang kenalan denganmu 😊 Ceritakan sedikit tentang hobimu dong.',
                        'Wah menarik! Kalau akhir pekan biasanya kamu ngapain?',
                        'Aku profil virtual Jodohku ya — latihan ngobrol di sini aman. Kamu tipe yang suka kopi atau teh?',
                        'Makasih sudah sapa! Biar makin seru, coba lengkapi profilmu lalu cari match asli juga ya ✨',
                    ],
                    'reply_delay_min_seconds' => 5,
                    'reply_delay_max_seconds' => 60,
                    'is_active' => true,
                ]
            );
        }
    }
}
