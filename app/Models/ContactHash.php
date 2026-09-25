<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactHash extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'phone_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** HMAC-SHA256 of the normalized phone (never the raw number). */
    public static function hash(string $normalizedPhone): string
    {
        return hash_hmac('sha256', $normalizedPhone, (string) config('app.key'));
    }
}
