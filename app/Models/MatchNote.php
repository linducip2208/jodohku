<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchNote extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'match_id', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userMatch(): BelongsTo
    {
        return $this->belongsTo(UserMatch::class, 'match_id');
    }
}
