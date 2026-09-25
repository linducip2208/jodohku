<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isSelf = $viewer && (int) $viewer->id === (int) $this->id;
        $isStaff = $viewer && method_exists($viewer, 'isStaff') && $viewer->isStaff();

        $model = $this->resource;
        $privacy = $model && $model->relationLoaded('profilePrivacy')
            ? $model->getRelation('profilePrivacy')
            : ($model ? $model->profilePrivacy()->first() : null);
        $showAge = $isSelf || $isStaff || ! $privacy || ($privacy->age_visibility?->value ?? 'public') === 'public';
        $showLocation = $isSelf || $isStaff || ! $privacy || ($privacy->location_visibility?->value ?? 'public') === 'public';
        $showOnline = $isSelf || $isStaff || ($privacy?->show_online_status ?? true);

        return [
            'id' => $this->id,
            'email' => $this->when($isSelf || $isStaff, $this->email),
            'display_name' => $this->displayName(),
            'username' => $this->username,
            'age' => $showAge ? $this->age() : null,
            'gender' => $this->gender?->value ?? $this->gender,
            'city' => $showLocation ? $this->city : null,
            'province' => $showLocation ? $this->province : null,
            'country' => $showLocation ? $this->country : null,
            'avatar' => $this->avatar_path,
            'is_verified' => (bool) $this->is_verified,
            'verification' => $this->resource instanceof User ? $this->verificationBadges() : null,
            'is_premium' => (bool) $this->is_premium,
            'is_online' => $showOnline ? (bool) $this->is_online : null,
            'last_active_at' => $showOnline ? $this->last_active_at : null,
            'account_type' => $this->account_type?->value ?? $this->account_type,
            'compatibility_score' => $this->when(isset($this->compatibility_score), $this->compatibility_score ?? null),
            'profile' => ProfileResource::make($this->whenLoaded('profile')),
            'photos' => $this->whenLoaded('photos'),
            'interests' => $this->whenLoaded('interests'),
            'created_at' => $this->created_at,
        ];
    }
}
