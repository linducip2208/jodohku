<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Follow extends Model
{
    use HasFactory;

    protected $fillable = ['follower_id', 'followed_id'];

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function followed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_id');
    }

    public static function existsBetween(int $followerId, int $followedId): bool
    {
        return static::where('follower_id', $followerId)->where('followed_id', $followedId)->exists();
    }

    public static function mutual(int $a, int $b): bool
    {
        return static::existsBetween($a, $b) && static::existsBetween($b, $a);
    }
}
