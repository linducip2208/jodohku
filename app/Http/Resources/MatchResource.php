<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $candidate = $this->resource instanceof \App\Models\User
            ? $this->resource
            : ($this->candidate ?? null);

        $breakdown = $this->match_breakdown ?? $this->breakdown ?? null;

        return [
            'user' => $candidate ? UserResource::make($candidate) : null,
            'compatibility_score' => $this->compatibility_score ?? $this->compatibility ?? $this->total_score ?? null,
            'breakdown' => $breakdown,
            'explanation' => is_array($breakdown) ? ($breakdown['explanation'] ?? $breakdown['summary'] ?? null) : null,
            'matched_at' => $this->matched_at ?? $this->created_at ?? null,
            'is_active' => $this->when(isset($this->is_active), $this->is_active),
        ];
    }
}
