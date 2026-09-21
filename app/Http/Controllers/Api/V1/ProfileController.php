<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ProfileViewed;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\ProfilePrivacy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function me(Request $request)
    {
        return response()->json(UserResource::make($request->user()->load(['profile', 'photos', 'videos', 'interests', 'partnerPreference'])));
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);
        $viewer = $request->user();
        $isSelf = $viewer && (int) $viewer->id === (int) $user->id;
        $user->load([
            'profile', 'interests',
            'photos' => fn ($q) => $q->ordered()->when(! ($isSelf || ($viewer && $viewer->isStaff())), fn ($qq) => $qq->where('status', 'approved')),
        ]);
        if (! $isSelf) {
            event(new ProfileViewed($user, $viewer));
        }

        return response()->json(UserResource::make($user));
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $this->authorize('update', $user);
        $data = $request->validated();

        DB::transaction(function () use ($user, $data, $request) {
            $userAttrs = collect($data)->only(['display_name', 'date_of_birth', 'gender', 'city', 'province', 'country', 'latitude', 'longitude'])->filter(fn ($v) => $v !== null)->all();
            if ($request->hasFile('avatar')) {
                $userAttrs['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
            }
            if ($userAttrs) {
                $user->update($userAttrs);
            }
            $profileAttrs = collect($data)->only([
                'headline', 'bio', 'occupation', 'education', 'religion', 'ethnicity', 'height_cm',
                'weight_kg', 'body_type', 'smoking', 'drinking', 'marital_status', 'children_count',
                'want_children', 'relationship_goal', 'languages', 'zodiac',
            ])->filter(fn ($v) => $v !== null)->all();
            if ($profileAttrs) {
                $user->profile()->updateOrCreate([], $profileAttrs);
            }
            if (isset($data['interests']) && is_array($data['interests'])) {
                $user->interests()->sync($data['interests']);
            }
            $privacyAttrs = collect($data)->only([
                'photos_visibility', 'videos_visibility', 'bio_visibility', 'location_visibility',
                'online_visibility', 'age_visibility', 'show_distance', 'show_online_status', 'allow_profile_views',
            ])->filter(fn ($v) => $v !== null)->all();
            if ($privacyAttrs) {
                ProfilePrivacy::updateOrCreate(['user_id' => $user->id], $privacyAttrs);
            }
        });

        return response()->json(UserResource::make($user->fresh(['profile', 'photos', 'interests'])));
    }
}
