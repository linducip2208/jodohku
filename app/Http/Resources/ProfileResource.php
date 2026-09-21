<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Profile */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'headline' => $this->headline,
            'bio' => $this->bio,
            'occupation' => $this->occupation,
            'education' => $this->education,
            'religion' => $this->religion,
            'ethnicity' => $this->ethnicity,
            'height_cm' => $this->height_cm,
            'weight_kg' => $this->weight_kg,
            'body_type' => $this->body_type,
            'marital_status' => $this->marital_status?->value ?? $this->marital_status,
            'relationship_goal' => $this->relationship_goal?->value ?? $this->relationship_goal,
            'languages' => $this->languages,
            'completeness' => method_exists($this->resource, 'completenessScore') ? $this->completenessScore() : null,
        ];
    }
}
