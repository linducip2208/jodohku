<?php

namespace Database\Seeders;

use App\Models\ChatTemplate;
use App\Models\ChatTrigger;
use App\Models\ChatTriggerAction;
use Illuminate\Database\Seeder;

class TriggerSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['code' => 'welcome_1', 'title' => 'Salam kenal baru daftar', 'body' => 'Halo {name}! Selamat datang di Jodohku ✨ Aku {virtual_name} (profil virtual). Sudah lengkapi profilmu? Ceritakan hobimu dong!', 'category' => 'greeting', 'language' => 'id', 'variables' => ['name', 'virtual_name']],
            ['code' => 'welcome_2', 'title' => 'Panduan awal', 'body' => 'Hai {name}! Tips cepat: upload 3 foto, isi kuesioner 2 menit, lalu mulai like. Aku {virtual_name} siap bantu latihan ngobrol 😊', 'category' => 'onboarding', 'language' => 'id', 'variables' => ['name', 'virtual_name']],
            ['code' => 'like_nudge', 'title' => 'Sapa setelah like', 'body' => 'Makasih likenya, {name}! Aku {virtual_name}. Kamu biasanya suka ngobrolin apa duluan?', 'category' => 'engagement', 'language' => 'id', 'variables' => ['name', 'virtual_name']],
            ['code' => 'match_congrats', 'title' => 'Selamat match', 'body' => 'Yeay, kamu match! Aku {virtual_name} ikut senang. Sapa duluan dengan pertanyaan ringan biar cair ✨', 'category' => 'match', 'language' => 'id', 'variables' => ['name', 'virtual_name']],
            ['code' => 'reactivation', 'title' => 'Kangen nih', 'body' => 'Halo {name}! Lama tak muncul — ada yang bisa aku bantu? Profil virtual Jodohku selalu di sini kok 😊', 'category' => 'reactivation', 'language' => 'id', 'variables' => ['name']],
        ];
        foreach ($templates as $t) {
            ChatTemplate::firstOrCreate(['code' => $t['code']], $t + ['is_active' => true]);
        }

        $triggers = [
            ['name' => 'Sapa pendaftar baru', 'description' => 'Kirim auto-chat virtual saat user.registered (cap harian 5, jam 08–22).', 'event' => 'user.registered', 'conditions' => [], 'priority' => 100, 'cooldown_minutes' => 60],
            ['name' => 'Sapa setelah like', 'description' => 'Follow-up ringan saat profile.liked.', 'event' => 'profile.liked', 'conditions' => [], 'priority' => 50, 'cooldown_minutes' => 120],
            ['name' => 'Rayakan match', 'description' => 'Ucapan selamat saat match.created.', 'event' => 'match.created', 'conditions' => [], 'priority' => 80, 'cooldown_minutes' => 30],
            ['name' => 'Reaktivasi 7 hari', 'description' => 'Sapa user pasif (dipicu scheduler).', 'event' => 'user.inactive_7d', 'conditions' => [], 'priority' => 20, 'cooldown_minutes' => 1440],
        ];
        foreach ($triggers as $tr) {
            $trigger = ChatTrigger::firstOrCreate(
                ['name' => $tr['name'], 'event' => $tr['event']],
                $tr + ['is_active' => true]
            );
            ChatTriggerAction::firstOrCreate(
                ['chat_trigger_id' => $trigger->id, 'action_type' => 'send_virtual_message', 'sort_order' => 1],
                ['payload' => ['template' => $tr['event'] === 'match.created' ? 'match_congrats' : ($tr['event'] === 'profile.liked' ? 'like_nudge' : 'welcome_1'), 'mode' => 'template']]
            );
        }
    }
}
