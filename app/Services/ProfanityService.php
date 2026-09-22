<?php

namespace App\Services;

use App\Models\ProfanityWord;
use Illuminate\Support\Facades\Cache;

class ProfanityService
{
    /** Leet-speak map for the normalization pass (detection only, never masking). */
    protected array $leet = ['4' => 'a', '@' => 'a', '0' => 'o', '1' => 'i', '3' => 'e', '5' => 's', '$' => 's', '7' => 't'];

    /**
     * Normalize for DETECTION: lowercase, unicode NFKC, leet-speak, collapsed
     * repeats (3+), punctuation → space. Word boundaries are preserved so
     * joined words cannot create false positives.
     */
    public function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        }
        $text = strtr($text, $this->leet);
        $text = (string) preg_replace('/(.)\1{2,}/u', '$1', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);

        return (string) preg_replace('/\s+/u', ' ', trim($text));
    }

    /** @return array{clean:string, hits:string[], count:int, categories:string[], max_severity:int, obfuscated:string[]} */
    public function censor(string $text): array
    {
        // Cache PLAIN ARRAYS only. Eloquent models must never cross a
        // serializing cache driver (database/file/redis): unserialize can
        // yield __PHP_Incomplete_Class and kill the request (seen in seed).
        $words = Cache::remember('profanity_words:v3', 300, fn () => ProfanityWord::active()
            ->with('category')->get(['id', 'profanity_category_id', 'word', 'replacement', 'is_regex', 'language', 'severity'])
            ->map(fn (ProfanityWord $w) => [
                'word' => (string) $w->word,
                'replacement' => $w->replacement,
                'is_regex' => (bool) $w->is_regex,
                'language' => $w->language,
                'severity' => (int) ($w->severity ?? 1),
                'category' => $w->category?->slug,
            ])->all());
        $hits = [];
        $categories = [];
        $maxSeverity = 0;
        $obfuscated = [];
        $clean = $text;
        foreach ($words as $entry) {
            $word = $entry['word'];
            $replacement = $entry['replacement'] ?? str_repeat('*', max(1, mb_strlen($word)));
            if ($entry['is_regex']) {
                $new = @preg_replace($word, $replacement, $clean);
                if ($new !== null && $new !== $clean) {
                    $hits[] = $word;
                    $clean = $new ?? $clean;
                }
            } else {
                $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($word, '/').'(?![\p{L}\p{N}_])/iu';
                $new = preg_replace($pattern, $replacement, $clean, -1, $count);
                if ($count > 0) {
                    $hits[] = $word;
                    $clean = $new ?? $clean;
                }
            }
            if (in_array($word, $hits, true)) {
                $maxSeverity = max($maxSeverity, $entry['severity']);
                if ($entry['category']) {
                    $categories[] = $entry['category'];
                }
            }
        }

        // Second pass on NORMALIZED text catches caps/punct/repeat/leet
        // obfuscation for detection (flags + risk) without touching masking.
        $seen = array_map(fn ($h) => mb_strtolower((string) $h), $hits);
        $normalized = $this->normalize($text);
        if ($normalized !== '' && $normalized !== mb_strtolower($text)) {
            foreach ($words as $entry) {
                if ($entry['is_regex'] || in_array(mb_strtolower($entry['word']), $seen, true)) {
                    continue;
                }
                $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote(mb_strtolower($entry['word']), '/').'(?![\p{L}\p{N}_])/u';
                if (preg_match($pattern, $normalized)) {
                    $obfuscated[] = $entry['word'];
                    $maxSeverity = max($maxSeverity, $entry['severity']);
                    if ($entry['category']) {
                        $categories[] = $entry['category'];
                    }
                }
            }
        }

        $hits = array_values(array_unique($hits));

        return [
            'clean' => $clean,
            'hits' => $hits,
            'count' => count($hits),
            'categories' => array_values(array_unique($categories)),
            'max_severity' => $maxSeverity,
            'obfuscated' => array_values(array_unique($obfuscated)),
        ];
    }

    public function containsProfanity(string $text): bool
    {
        return $this->censor($text)['count'] > 0;
    }
}
