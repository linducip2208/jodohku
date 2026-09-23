<?php

namespace App\Services;

use App\Models\User;

/**
 * Grounded matchmaker: only recommends REAL profiles returned by
 * DiscoveryService. Never invents names, photos, or contact details.
 */
class AiMatchmakerService
{
    public function __construct(
        protected DiscoveryService $discovery,
        protected MatchingEngine $engine,
        protected AiService $ai,
    ) {}

    /** @return array{picks:array, explanation:string} */
    public function recommend(User $user, string $question = '', int $limit = 5): array
    {
        $candidates = $this->discovery->discover($user, [], $limit);
        $items = $candidates->getCollection()->map(function (User $c) use ($user) {
            $why = app(MatchExplanation::class)->for($user, $c);
            $profile = $c->profile;

            return [
                'user_id' => $c->id,
                'display_name' => $c->displayName(),
                'age' => $c->age(),
                'city' => $c->city,
                'headline' => $profile?->headline,
                'compatibility' => $c->compatibility_score ?? null,
                'why' => array_slice($why['reasons'], 0, 3),
            ];
        })->all();

        if (empty($items)) {
            return ['picks' => [], 'explanation' => 'Belum ada kandidat yang cocok. Lengkapi profil dan preferensimu dulu ya.'];
        }

        // Build grounded prompt: model may ONLY use supplied profiles
        $list = collect($items)->map(fn ($i) => "- {$i['display_name']} ({$i['age']} th, {$i['city']}) skor {$i['compatibility']}: ".implode('; ', $i['why']))->implode("\n");
        $prompt = 'Kamu adalah mak comblang '.config('app.name').'. User bertanya: "'.$question.'".'."\n".
            "Berikut kandidat NYATA (jangan tambah nama lain, jangan karang kontak/foto):\n{$list}\n".
            'Jawab singkat dalam Bahasa Indonesia: sebutkan 3 teratas dan alasan tiap pilihan (max 120 kata).';

        try {
            $res = $this->ai->chat($prompt, ['max_tokens' => 300], $user, 'matchmaker');
            $explanation = (string) $res['text'];
        } catch (\Throwable) {
            $top = $items[0];
            $explanation = "Rekomendasi teratas: {$top['display_name']} (skor {$top['compatibility']}). ".implode('. ', array_slice($top['why'], 0, 2));
        }

        return ['picks' => $items, 'explanation' => $explanation];
    }
}
