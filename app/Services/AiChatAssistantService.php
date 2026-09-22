<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMember;
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
        $lines = array_slice($lines, 0, $count);
        if (count($lines) < $count) {
            // Pad short AI output with grounded deterministic openers.
            $lines = array_slice(array_values(array_unique(array_merge($lines, $this->openers($user, $candidate, $count)))), 0, $count);
        }

        return $lines ?: ['Boleh kenalan? Profilmu menarik!'];
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

    /** Opening messages grounded on shared interests — deterministic, AI-free fallback. */
    public function openers(User $user, User $candidate, int $count = 3): array
    {
        $mine = $user->interests->pluck('name');
        $theirs = $candidate->interests->pluck('name');
        $shared = $mine->intersect($theirs)->values()->take(2);

        $openers = [];
        foreach ($shared as $interest) {
            $openers[] = "Aku lihat kamu juga suka {$interest}, ada rekomendasi {$interest} kesukaanmu?";
        }
        if ($candidate->profile?->occupation) {
            $openers[] = "Kerjaan {$candidate->profile->occupation} pasti seru, sering lelah atau asik?";
        }
        if ($candidate->city && $user->city && strtolower($candidate->city) === strtolower($user->city)) {
            $openers[] = "Kita satu kota ({$candidate->city})! Ada tempat favorit di sini?";
        }
        $openers[] = "Halo {$candidate->displayName()}! Aku kepo sama profil kamu, cocok banget kita kenalan 💬";
        $openers[] = 'Boleh tahu hal apa yang paling kamu suka lakukan akhir-akhir ini?';

        return array_slice(array_values(array_unique($openers)), 0, $count);
    }

    /** Catch-up digest: what you missed since you last read the thread. */
    public function catchUp(Conversation $conversation, User $user, int $limit = 30): array
    {
        $member = ConversationMember::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)->first();
        $since = $member?->last_read_at;
        $items = $conversation->messages()->with('sender')
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->latest('id')->limit($limit)->get()->reverse()->values();
        if ($items->isEmpty()) {
            return ['has_updates' => false, 'count' => 0, 'digest' => null];
        }
        $history = $items->map(fn ($m) => ($m->sender?->displayName() ?? '?').': '.mb_substr((string) $m->body, 0, 150))->implode("\n");
        $prompt = "Ringkas update terbaru percakapan berikut maksimal 3 kalimat, Bahasa Indonesia:\n{$history}";

        try {
            $res = $this->ai->chat($prompt, ['max_tokens' => 120], $user, 'catch_up');
            $digest = trim((string) $res['text']) ?: null;
        } catch (\Throwable) {
            $digest = null;
        }

        return ['has_updates' => true, 'count' => $items->count(), 'digest' => $digest];
    }

    /** Short AI summary of a conversation thread for quick context. */
    public function digest(Conversation $conversation, User $user): string
    {
        $history = $conversation->messages()->latest('id')->limit(20)->get()->reverse()
            ->map(fn ($m) => mb_substr((string) $m->body, 0, 150))
            ->implode("\n");
        $prompt = "Ringkas percakapan berikut maksimal 3 kalimat, Bahasa Indonesia:\n{$history}";

        try {
            $res = $this->ai->chat($prompt, ['max_tokens' => 100], $user, 'digest');

            return trim((string) $res['text']) ?: 'Tidak ada ringkasan yang tersedia.';
        } catch (\Throwable) {
            return 'Tidak ada ringkasan yang tersedia saat ini.';
        }
    }
}
