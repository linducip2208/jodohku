<?php

namespace App\Services;

use App\Enums\CreditTxnType;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function balance(User $user): int
    {
        return (int) (CreditWallet::where('user_id', $user->id)->value('balance') ?? 0);
    }

    public function wallet(User $user): CreditWallet
    {
        return CreditWallet::firstOrCreate(['user_id' => $user->id], ['balance' => 0]);
    }

    public function award(User $user, int $amount, string $description = '', array $meta = []): CreditTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Award amount must be positive.');
        }

        return $this->record($user, CreditTxnType::Earn, $amount, $description, $meta);
    }

    public function spend(User $user, int $amount, string $description = '', array $meta = []): CreditTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Spend amount must be positive.');
        }

        return $this->record($user, CreditTxnType::Spend, $amount, $description, $meta);
    }

    public function record(User $user, CreditTxnType $type, int $amount, string $description = '', array $meta = []): CreditTransaction
    {
        return DB::transaction(function () use ($user, $type, $amount, $description, $meta) {
            $wallet = CreditWallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = CreditWallet::create(['user_id' => $user->id, 'balance' => 0]);
            }
            $delta = $type->isCredit() ? abs($amount) : -abs($amount);
            $newBalance = $wallet->balance + $delta;
            if ($newBalance < 0) {
                throw new \RuntimeException('Insufficient credits.');
            }
            $wallet->update([
                'balance' => $newBalance,
                'lifetime_earned' => $wallet->lifetime_earned + ($delta > 0 ? $delta : 0),
                'lifetime_spent' => $wallet->lifetime_spent + ($delta < 0 ? abs($delta) : 0),
            ]);

            return CreditTransaction::create([
                'credit_wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $delta,
                'balance_after' => $newBalance,
                'description' => $description,
                'metadata' => $meta ?: null,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
            ]);
        });
    }

    public function expire(User $user, int $amount): CreditTransaction
    {
        return $this->record($user, CreditTxnType::Expire, $amount, 'Credit expiry');
    }
}
