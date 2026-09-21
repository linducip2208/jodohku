<?php

namespace App\Models;

use App\Enums\CreditTxnType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class CreditTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_wallet_id', 'user_id', 'type', 'amount',
        'balance_after', 'reference_type', 'reference_id', 'description', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => CreditTxnType::class,
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CreditWallet::class, 'credit_wallet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public static function record(User $user, CreditTxnType $type, int $amount, array $attributes = []): self
    {
        return DB::transaction(function () use ($user, $type, $amount, $attributes) {
            $wallet = CreditWallet::forUser($user);
            $delta = $type->isCredit() ? abs($amount) : -abs($amount);
            $wallet->increment('balance', $delta);

            if ($delta > 0) {
                $wallet->increment('lifetime_earned', $delta);
            } else {
                $wallet->increment('lifetime_spent', abs($delta));
            }

            return static::create(array_merge($attributes, [
                'credit_wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $delta,
                'balance_after' => $wallet->fresh()->balance,
            ]));
        });
    }
}
