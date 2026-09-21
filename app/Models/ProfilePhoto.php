<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfilePhoto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'path', 'thumbnail_path', 'sort_order',
        'is_primary', 'is_approved', 'is_private',
        'status', 'file_hash', 'width', 'height', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_approved' => 'boolean',
            'is_private' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<ProfilePhoto> $query */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /** @param Builder<ProfilePhoto> $query */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', 'pending')->oldest('id');
    }

    /** @param Builder<ProfilePhoto> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
