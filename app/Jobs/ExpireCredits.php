<?php

namespace App\Jobs;

use App\Enums\CreditTxnType;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ExpireCredits implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $batch = 200) {}

    public function handle(): void
    {
        // Expire bonus credits older than 90 days (FIFO-lite): cap at batch
        $rows = CreditTransaction::where('type', CreditTxnType::Bonus->value)
            ->where('created_at', '<', now()->subDays(90))
            ->where('amount', '>', 0)
            ->limit($this->batch)->get();
        foreach ($rows as $row) {
            DB::transaction(function () use ($row) {
                $wallet = CreditWallet::where('user_id', $row->user_id)->lockForUpdate()->first();
                if (! $wallet || $wallet->balance <= 0) {
                    return;
                }
                $take = min($wallet->balance, (int) $row->amount);
                $wallet->decrement('balance', $take);
                $wallet->increment('lifetime_spent', $take);
                CreditTransaction::create([
                    'credit_wallet_id' => $wallet->id,
                    'user_id' => $row->user_id,
                    'type' => CreditTxnType::Expire,
                    'amount' => -$take,
                    'balance_after' => $wallet->fresh()->balance,
                    'description' => 'Bonus credit expiry',
                ]);
            });
        }
    }
}
