<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'gateway' => $this->gateway,
            'amount' => $this->amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'status' => $this->status?->value ?? $this->status,
            'paid_at' => $this->paid_at,
            'expires_at' => $this->expires_at,
            'items' => $this->whenLoaded('items'),
            'created_at' => $this->created_at,
        ];
    }
}
