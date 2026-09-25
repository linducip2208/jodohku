<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateAccount extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = ['user_id', 'code', 'status', 'commission_rate'];

    protected function casts(): array
    {
        return ['commission_rate' => 'decimal:4'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function balance(): float
    {
        return (float) $this->commissions()->where('status', AffiliateCommission::STATUS_APPROVED)->sum('amount');
    }
}
