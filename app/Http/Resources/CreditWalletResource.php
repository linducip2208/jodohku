<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CreditWallet */
class CreditWalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'balance' => (int) $this->balance,
            'lifetime_earned' => (int) ($this->lifetime_earned ?? 0),
            'lifetime_spent' => (int) ($this->lifetime_spent ?? 0),
            'transactions' => $this->whenLoaded('transactions'),
        ];
    }
}
