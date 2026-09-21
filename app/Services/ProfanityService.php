<?php

namespace App\Services;

use App\Models\ProfanityWord;
use Illuminate\Support\Facades\Cache;

class ProfanityService
{
    /** @return array{clean:string, hits:string[], count:int} */
    public function censor(string $text): array
    {
        $words = Cache::remember('profanity_words', 300, fn () => ProfanityWord::active()->get(['word', 'replacement', 'is_regex', 'language'])->all());
        $hits = [];
        $clean = $text;
        foreach ($words as $entry) {
            $word = $entry->word;
            $replacement = $entry->replacement ?? str_repeat('*', max(1, mb_strlen($word)));
            if ($entry->is_regex) {
                $new = @preg_replace($word, $replacement, $clean);
                if ($new !== null && $new !== $clean) {
                    $hits[] = $word;
                    $clean = $new;
                }
            } else {
                $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($word, '/').'(?![\p{L}\p{N}_])/iu';
                $new = preg_replace($pattern, $replacement, $clean, -1, $count);
                if ($count > 0) {
                    $hits[] = $word;
                    $clean = $new ?? $clean;
                }
            }
        }

        return ['clean' => $clean, 'hits' => array_values(array_unique($hits)), 'count' => count($hits)];
    }

    public function containsProfanity(string $text): bool
    {
        return $this->censor($text)['count'] > 0;
    }
}
