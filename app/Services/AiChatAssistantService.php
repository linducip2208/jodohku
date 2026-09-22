<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\QuestionnaireAnswer;
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
            ->whereNotIn('status', ['deleted', 'moderated'])
            ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $user->id))
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->latest('id')->limit($limit)->get()->reverse()->values();
        if ($items->isEmpty()) {
            return ['has_updates' => false, 'count' => 0, 'digest' => null, 'message_ids' => [], 'unread_first_id' => null, 'mentions' => []];
        }
        $history = $items->map(fn ($m) => ($m->sender?->displayName() ?? '?').': '.mb_substr((string) $m->body, 0, 150))->implode("\n");
        $prompt = "Ringkas update terbaru percakapan berikut maksimal 3 kalimat, Bahasa Indonesia:\n{$history}";

        try {
            $res = $this->ai->chat($prompt, ['max_tokens' => 120], $user, 'catch_up');
            $digest = trim((string) $res['text']) ?: null;
        } catch (\Throwable) {
            $digest = null;
        }
        // Extractive fallback when AI is unavailable: first + last unread slice.
        if ($digest === null) {
            $first = $items->first();
            $last = $items->last();
            $digest = $items->count() === 1
                ? mb_substr((string) $first->body, 0, 160)
                : mb_substr((string) $first->body, 0, 80).' … '.mb_substr((string) $last->body, 0, 80).' ('.$items->count().' pesan belum dibaca)';
        }

        return [
            'has_updates' => true,
            'count' => $items->count(),
            'digest' => $digest,
            'message_ids' => $items->pluck('id')->values()->all(),
            'unread_first_id' => $items->first()?->id,
            'mentions' => [],
        ];
    }

    /**
     * Taaruf topic suggestions grounded ONLY on data both users can already
     * see (shared interests, profile, preferences, questionnaire answers).
     * Deterministic builder first; AI only rephrases when available.
     *
     * @return array<int, array{topic:string, why:string, question:string}>
     */
    public function taarufTopics(User $user, User $candidate, int $count = 5): array
    {
        $topics = $this->groundedTopics($user, $candidate);
        $topics = array_slice($topics, 0, max(1, $count));

        try {
            $res = $this->ai->chat(
                'Ubah topik berikut menjadi pertanyaan pembuka taaruf yang sopan dalam Bahasa Indonesia, satu per baris (maks '.count($topics)."):\n".
                implode("\n", array_map(fn ($t, $i) => ($i + 1).'. '.$t['topic'].' ('.$t['why'].')', $topics, array_keys($topics))),
                ['max_tokens' => 300], $user, 'taaruf_topics'
            );
            $lines = array_values(array_filter(array_map(fn ($l) => trim((string) preg_replace('/^[\d\-\.\)\s]+/', '', (string) $l)), preg_split('/\r?\n/', trim((string) $res['text'])))));
            foreach ($topics as $i => $t) {
                if (isset($lines[$i]) && $lines[$i] !== '') {
                    $topics[$i]['question'] = $lines[$i];
                }
            }
        } catch (\Throwable) {
            // Deterministic fallback below is already complete.
        }

        return array_values($topics);
    }

    /** @return array<int, array{topic:string, why:string, question:string}> */
    protected function groundedTopics(User $user, User $candidate): array
    {
        $topics = [];
        $user->loadMissing(['profile', 'partnerPreference', 'interests']);
        $candidate->loadMissing(['profile', 'partnerPreference', 'interests']);

        $shared = $user->interests->pluck('name')->intersect($candidate->interests->pluck('name'))->values();
        foreach ($shared->take(2) as $interest) {
            $topics[] = [
                'topic' => 'Minat yang sama: '.$interest,
                'why' => 'Kalian berdua menyukai '.$interest,
                'question' => 'Aku lihat kita sama-sama suka '.$interest.' — biasanya menikmati itu dengan cara apa?',
            ];
        }

        $myGoal = $user->profile?->relationship_goal?->value ?? $user->profile?->relationship_goal;
        $theirGoal = $candidate->profile?->relationship_goal?->value ?? $candidate->profile?->relationship_goal;
        if ($myGoal && $theirGoal) {
            $topics[] = [
                'topic' => 'Visi pernikahan',
                'why' => $myGoal === $theirGoal ? 'Tujuan hubungan kalian sama' : 'Tujuan hubungan kalian perlu diselaraskan',
                'question' => 'Dalam 2 tahun ke depan, seperti apa gambaran pernikahan ideal menurutmu?',
            ];
        }
        if ($user->profile?->want_children !== null || $candidate->profile?->want_children !== null) {
            $topics[] = [
                'topic' => 'Rencana anak',
                'why' => 'Pandangan soal anak penting dibahas sejak taaruf',
                'question' => 'Bagaimana pandanganmu tentang momongan setelah menikah nanti?',
            ];
        }
        if ($user->city && $candidate->city) {
            $same = strtolower($user->city) === strtolower($candidate->city);
            $topics[] = [
                'topic' => 'Tempat tinggal',
                'why' => $same ? 'Sama-sama di '.$candidate->city : 'Beda kota ('.$user->city.' & '.$candidate->city.')',
                'question' => $same
                    ? 'Kalau sudah menikah nanti, kamu ingin tetap tinggal di '.$candidate->city.'?'
                    : 'Bagaimana menurutmu soal relokasi setelah menikah nanti?',
            ];
        }
        if ($candidate->profile?->occupation) {
            $topics[] = [
                'topic' => 'Pekerjaan & keuangan',
                'why' => 'Pasangan bekerja sebagai '.$candidate->profile->occupation,
                'question' => 'Bagaimana kamu biasanya mengatur keuangan keluarga? Joint atau masing-masing?',
            ];
        }

        foreach ($this->questionnaireDiffs($user, $candidate, 2) as $diff) {
            $topics[] = [
                'topic' => 'Perbedaan pandangan: '.$diff['category'],
                'why' => 'Jawaban kuesioner kalian berbeda di "'.$diff['question'].'"',
                'question' => 'Aku penasaran dengan pandanganmu soal '.mb_strtolower($diff['question']),
            ];
        }

        $topics[] = [
            'topic' => 'Komunikasi',
            'why' => 'Fondasi taaruf yang sehat',
            'question' => 'Kalau ada masalah nanti, kamu lebih suka dibicarakan langsung atau diberi waktu dulu?',
        ];

        return $topics;
    }

    /** @return array<int, array{category:string, question:string}> */
    public function questionnaireDiffs(User $a, User $b, int $limit = 3): array
    {
        $aAnswers = QuestionnaireAnswer::where('user_id', $a->id)->with('question')->get()->keyBy('question_id');
        $bAnswers = QuestionnaireAnswer::where('user_id', $b->id)->with('question')->get()->keyBy('question_id');
        $diffs = [];
        foreach ($aAnswers as $qid => $ans) {
            $other = $bAnswers->get($qid);
            if (! $other) {
                continue;
            }
            $mine = $ans->answer_value ?? $ans->answer_text ?? $ans->question_option_id;
            $theirs = $other->answer_value ?? $other->answer_text ?? $other->question_option_id;
            if ((string) $mine !== (string) $theirs) {
                $cat = $ans->question?->category_key;
                if ($cat instanceof \BackedEnum) {
                    $cat = $cat->value;
                }
                $diffs[] = [
                    'category' => $cat ?? $ans->question?->category?->slug ?? 'umum',
                    'question' => $ans->question?->question_text ?? 'pertanyaan kuesioner',
                ];
            }
            if (count($diffs) >= $limit) {
                break;
            }
        }

        return $diffs;
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
