<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfanityWord extends Model
{
    use HasFactory;

    protected $fillable = [
        'profanity_category_id', 'word', 'replacement', 'language',
        'severity', 'is_regex', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_regex' => 'boolean', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProfanityCategory::class, 'profanity_category_id');
    }

    /** @param Builder<ProfanityWord> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function censor(string $text): string
    {
        $words = static::active()->get(['word', 'replacement', 'is_regex']);

        foreach ($words as $entry) {
            $replacement = $entry->replacement ?? str_repeat('*', mb_strlen($entry->word));
            if ($entry->is_regex) {
                $text = (string) preg_replace($entry->word, $replacement, $text);
            } else {
                $text = str_ireplace($entry->word, $replacement, $text);
            }
        }

        return $text;
    }
}
