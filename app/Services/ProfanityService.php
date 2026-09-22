<?php

namespace App\Services;

use App\Models\ProfanityWord;
use Illuminate\Support\Facades\Cache;

class ProfanityService
{
    /** Leet-speak map for the normalization pass (detection only, never masking). */
    protected array $leet = ['4' => 'a', '@' => 'a', '0' => 'o', '1' => 'i', '3' => 'e', '5' => 's', '$' => 's', '7' => 't', '!' => 'i', '+' => 't'];

    /** Cyrillic/Greek homoglyphs that visually mimic Latin letters. */
    protected array $homoglyph = [
        'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'х' => 'x',
        'у' => 'y', 'к' => 'k', 'м' => 'm', 'н' => 'h', 'т' => 't', 'в' => 'b',
        'α' => 'a', 'ε' => 'e', 'ο' => 'o', 'ρ' => 'p', 'ς' => 's', 'ι' => 'i',
    ];

    /**
     * Normalize for DETECTION: lowercase, unicode NFKC, zero-width strip,
     * homoglyph fold, leet-speak, collapsed repeats, defang unfold,
     * spaced-letter join, punctuation → space. Word boundaries preserved.
     */
    public function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        }
        // Strip zero-width / invisible chars used to split banned words.
        $text = (string) preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]/u', '', $text);
        // Fold homoglyphs.
        $text = strtr($text, $this->homoglyph);
        // Unfold common defang: hxxp→http, [.]/(dot)/titik→.
        $text = (string) preg_replace('/\bhxxps?\b/u', 'http', $text);
        $text = (string) preg_replace('/\bhxxp\b/u', 'http', $text);
        $text = (string) preg_replace('/\[\s*\.\s*\]|\(\s*dot\s*\)|\{\s*dot\s*\}|\btitik\b|\bdot\b/u', '.', $text);
        $text = (string) preg_replace('/\[\s*:\s*\]|\(\s*:\s*\)/u', ':', $text);
        $text = (string) preg_replace('/\[\s*\/\s*\]/u', '/', $text);
        $text = strtr($text, $this->leet);
        $text = (string) preg_replace('/(.)\1{2,}/u', '$1', $text);
        // Join spaced single letters: "a n j i n g" → "anjing" for detection.
        $text = (string) preg_replace_callback('/\b(?:\p{L}[\s\.\-_]+){2,}\p{L}\b/u', fn ($m) => (string) preg_replace('/[\s\.\-_]+/u', '', $m[0]), $text);
        // Indonesian number-words often used to dodge phone detection.
        $text = $this->foldNumberWords($text);
        $text = (string) preg_replace('/[^\p{L}\p{N}\s.:@\/]+/u', ' ', $text);

        return (string) preg_replace('/\s+/u', ' ', trim($text));
    }

    /** Fold "nol/delapan/..." number words to digits for scam detection. */
    protected function foldNumberWords(string $text): string
    {
        $map = [
            'nol' => '0', 'satu' => '1', 'dua' => '2', 'tiga' => '3', 'empat' => '4',
            'lima' => '5', 'enam' => '6', 'tujuh' => '7', 'delapan' => '8', 'sembilan' => '9',
        ];
        foreach ($map as $word => $digit) {
            $text = (string) preg_replace('/\b'.preg_quote($word, '/').'\b/u', $digit, $text);
        }

        return $text;
    }

    /** Admin dry-run: preview what censor() would do without persisting. */
    public function preview(string $text): array
    {
        return $this->censor($text);
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

        // Second pass on NORMALIZED text catches caps/punct/repeat/leet/
        // zero-width/spaced-letter obfuscation. Obfuscated hits are ALSO
        // masked with a flexible pattern so visual bypasses don't leak.
        $seen = array_map(fn ($h) => mb_strtolower((string) $h), $hits);
        $normalized = $this->normalize($text);
        if ($normalized !== '') {
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
                    // Flexible mask in original: allow punct/space/repeat between letters.
                    $replacement = $entry['replacement'] ?? str_repeat('*', max(1, mb_strlen($entry['word'])));
                    $chars = preg_split('//u', $entry['word'], -1, PREG_SPLIT_NO_EMPTY);
                    if ($chars) {
                        $flex = implode('[^\p{L}\p{N}]{0,3}', array_map(fn ($c) => preg_quote(mb_strtolower($c), '/'), $chars));
                        $flexPattern = '/(?<![\p{L}\p{N}_])'.$flex.'(?![\p{L}\p{N}_])/iu';
                        $masked = preg_replace($flexPattern, $replacement, $clean);
                        if ($masked !== null && $masked !== $clean) {
                            $clean = $masked;
                            $hits[] = $entry['word'];
                        }
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
