<?php

namespace App\Http\Resources;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Profile */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $owner = $this->resource?->user;
        $isSelf = $viewer && $owner && (int) $viewer->id === (int) $owner->id;
        $isStaff = $viewer && method_exists($viewer, 'isStaff') && $viewer->isStaff();
        $showBio = $isSelf || $isStaff;
        if (! $showBio && $owner) {
            try {
                $privacy = $owner->relationLoaded('profilePrivacy')
                    ? $owner->getRelation('profilePrivacy')
                    : $owner->profilePrivacy()->first();
                $vis = $privacy?->bio_visibility;
                $vis = $vis?->value ?? (string) ($vis ?? 'public');
                $showBio = $vis === 'public' || ($vis === 'members_only' && (bool) $viewer);
            } catch (\Throwable) {
                $showBio = true;
            }
        }

        return [
            'headline' => $this->headline,
            'bio' => $showBio ? $this->bio : null,
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
