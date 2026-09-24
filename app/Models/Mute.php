<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mute extends Model
{
    use HasFactory;

    protected $fillable = ['muter_id', 'muted_id'];

    public function muter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muter_id');
    }

    public function muted(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muted_id');
    }

    public static function existsBetween(int $muterId, int $mutedId): bool
    {
        return static::where('muter_id', $muterId)->where('muted_id', $mutedId)->exists();
    }
}
