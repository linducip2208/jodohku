<?php

namespace App\Models;

use App\Enums\SuccessStoryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuccessStory extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'partner_name', 'story', 'photo_path', 'status', 'published_at'];

    protected function casts(): array
    {
        return [
            'status' => SuccessStoryStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<SuccessStory> $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', SuccessStoryStatus::Published);
    }
}
