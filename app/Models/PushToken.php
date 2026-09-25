<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushToken extends Model
{
    use HasFactory;

    public const PLATFORMS = ['web', 'android', 'ios'];

    protected $fillable = ['user_id', 'platform', 'token', 'meta', 'disabled_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'disabled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeLive($query)
    {
        return $query->whereNull('disabled_at');
    }
}
