<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudRiskScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'score', 'level', 'signals', 'ip_address', 'device_fingerprint', 'scored_at',
    ];

    protected function casts(): array
    {
        return ['signals' => 'array', 'scored_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isHighRisk(): bool
    {
        return $this->score >= 70 || $this->level === 'high';
    }
}
