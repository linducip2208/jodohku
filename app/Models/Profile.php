<?php

namespace App\Models;

use App\Enums\MaritalStatus;
use App\Enums\RelationshipGoal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'headline', 'bio', 'occupation', 'education', 'religion',
        'ethnicity', 'height_cm', 'weight_kg', 'body_type', 'smoking', 'drinking',
        'marital_status', 'children_count', 'want_children', 'relationship_goal',
        'languages', 'zodiac', 'is_complete', 'is_featured', 'prompts',
    ];

    protected function casts(): array
    {
        return [
            'marital_status' => MaritalStatus::class,
            'relationship_goal' => RelationshipGoal::class,
            'is_complete' => 'boolean',
            'is_featured' => 'boolean',
            'prompts' => 'array',
        ];
    }

    /** Fixed prompt questions (Indonesian); answers stored as {question: answer}. */
    public const PROMPT_QUESTIONS = [
        'Akhir pekan ideal menurutmu?',
        'Hal kecil yang membuatmu bahagia?',
        'Tujuan hubungan yang kamu cari?',
        'Kebiasaan yang tidak bisa kamu tinggalkan?',
        'Kencan pertama yang berkesan versi kamu?',
        'Nilai yang paling penting dalam pasangan?',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Single source of truth for profile strength (0-100), shared by the
     * member UI, the completion prompts, and MatchingEngine behaviorScore.
     *
     * 7 base fields (9 pts each = 63) + approved photo/avatar (15) +
     * interests (7 for >=1, +5 for >=3) + substantial bio >=50 chars (10).
     */
    public function completenessScore(): int
    {
        $fields = ['headline', 'bio', 'occupation', 'education', 'religion', 'height_cm', 'relationship_goal'];
        $filled = collect($fields)->filter(fn ($f) => ! empty($this->{$f}))->count();
        $score = $filled * 9;

        $hasPhoto = ! empty($this->user?->avatar_path);
        if (! $hasPhoto && $this->user) {
            try {
                $hasPhoto = $this->user->photos()->where('status', 'approved')->exists();
            } catch (\Throwable) {
            }
        }
        if ($hasPhoto) {
            $score += 15;
        }

        try {
            $interestCount = $this->user ? $this->user->interests()->count() : 0;
        } catch (\Throwable) {
            $interestCount = 0;
        }
        if ($interestCount >= 3) {
            $score += 12;
        } elseif ($interestCount >= 1) {
            $score += 7;
        }

        if (mb_strlen(trim((string) $this->bio)) >= 50) {
            $score += 10;
        }

        return min(100, (int) round($score));
    }
}
