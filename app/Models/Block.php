<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    use HasFactory;

    protected $fillable = ['blocker_id', 'blocked_id', 'reason'];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    public static function existsBetween(int $userA, int $userB): bool
    {
        return static::where(fn ($q) => $q
            ->where('blocker_id', $userA)->where('blocked_id', $userB))
            ->orWhere(fn ($q) => $q
                ->where('blocker_id', $userB)->where('blocked_id', $userA))
            ->exists();
    }
}
