<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;

class AiChatAssistantService
{
    public function __construct(protected AiService $ai) {}

    /** @return string[] */
    public function suggestedReplies(Conversation $conversation, User $user, int $count = 3): array
    {
        $history = $conversation->messages()->latest('id')->limit(10)->get()->reverse()
            ->map(fn ($m) => ($m->sender_id === $user->id ? 'Saya: ' : 'Dia: ').mb_substr((string) $m->body, 0, 200))
            ->implode("\n");
        $prompt = "Percakapan:\n{$history}\n\nBerikan {$count} saran balasan singkat, sopan, natural dalam Bahasa Indonesia. Satu per baris, tanpa nomor berlebih, tanpa konten vulgar.";

        try {
            $res = $this->ai->chat($prompt, ['max_tokens' => 200], $user, 'suggested_replies');
        } catch (\Throwable) {
            return ['Haha seru juga ceritanya 😄', 'Oh iya? Ceritain lebih lanjut dong', 'Kapan-kapan kita ngobrol lagi ya?'];
        }

        $lines = preg_split('/\r?\n/', trim((string) $res['text']));
        $lines = array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/^[\d\-\.\)\s]+/', '', (string) $l)), $lines)));

        return array_slice($lines, 0, $count) ?: ['Boleh ceritakan lebih banyak tentang dirimu?'];
    }

    /** @return string[] */
    public function icebreakers(User $user, User $candidate, int $count = 3): array
    {
        $hints = [];
        if ($candidate->city) {
            $hints[] = 'tinggal di '.$candidate->city;
        }
        if ($candidate->profile?->occupation) {
            $hints[] = 'bekerja sebagai '.$candidate->profile->occupation;
        }
        if ($candidate->profile?->headline) {
            $hints[] = '"'.$candidate->profile->headline.'"';
        }
        $ctx = $hints ? ' (info: '.implode(', ', $hints).')' : '';
        $prompt = "Buatkan {$count} kalimat pembuka chat yang sopan dan menarik untuk menyapa {$candidate->displayName()}{$ctx}. Bahasa Indonesia santai, satu per baris.";

        try {
            $res = $this->ai->chat($prompt, ['max_tokens' => 150], $user, 'icebreaker');
        } catch (\Throwable) {
            return ["Halo {$candidate->displayName()}! Salam kenal 👋", 'Boleh kenalan? Profilmu menarik!', 'Hai! Lagi sibuk apa akhir-akhir ini?'];
        }
        $lines = array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/^[\d\-\.\)\s]+/', '', (string) $l)), preg_split('/\r?\n/', trim((string) $res['text'])))));

        return array_slice($lines, 0, $count);
    }

    public function rewrite(string $draft, string $tone = 'friendly', ?User $user = null): string
    {
        $prompt = "Tulis ulang pesan berikut dengan nada {$tone}, tetap sopan dan natural, Bahasa Indonesia:\n\"{$draft}\"";

        try {
            $res = $this->ai->chat($prompt, ['max_tokens' => 200], $user, 'rewrite');
        } catch (\Throwable) {
            return $draft;
        }

        return trim((string) $res['text']) ?: $draft;
    }
}
