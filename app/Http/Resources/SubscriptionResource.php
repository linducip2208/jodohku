<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => $this->whenLoaded('plan', fn () => $this->plan),
            'plan_code' => $this->plan?->code,
            'status' => $this->status?->value ?? $this->status,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'trial_ends_at' => $this->trial_ends_at,
            'auto_renew' => (bool) $this->auto_renew,
            'created_at' => $this->created_at,
        ];
    }
}
