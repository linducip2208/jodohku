<?php

namespace App\Http\Controllers\Member;

use App\Events\ProfileViewed;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\ProfilePrivacy;
use App\Models\User;
use App\Services\VirtualMemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request, User $user, VirtualMemberService $virtual)
    {
        $this->authorize('view', $user);
        $viewer = $request->user();
        $isSelf = $viewer && (int) $viewer->id === (int) $user->id;
        $user->load([
            'profile', 'videos', 'interests', 'profilePrivacy',
            'photos' => fn ($q) => $q->ordered()->when(! ($isSelf || ($viewer && $viewer->isStaff())), fn ($qq) => $qq->where('status', 'approved')),
        ]);
        if ($viewer && ! $isSelf) {
            event(new ProfileViewed($user, $viewer));
        }

        $data = [
            'user' => UserResource::make($user),
            'synthetic_label' => $user->isReal() ? null : $virtual->label($user),
        ];

        return $request->wantsJson() ? response()->json($data) : view('member.profile.show', $data);
    }

    public function edit(Request $request)
    {
        $user = $request->user()->load(['profile', 'photos', 'videos', 'interests', 'partnerPreference', 'profilePrivacy']);

        return $request->wantsJson()
            ? response()->json(UserResource::make($user))
            : view('member.profile.edit', ['user' => $user]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $this->authorize('update', $user);
        $data = $request->validated();

        DB::transaction(function () use ($user, $data, $request) {
            $userAttrs = collect($data)->only([
                'display_name', 'date_of_birth', 'gender', 'city', 'province', 'country', 'latitude', 'longitude',
            ])->filter(fn ($v) => $v !== null)->all();
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

            if (array_key_exists('interests', $data) && is_array($data['interests'])) {
                $user->interests()->sync($data['interests']);
            }

            $privacyAttrs = collect($data)->only([
                'photos_visibility', 'videos_visibility', 'bio_visibility', 'location_visibility',
                'online_visibility', 'age_visibility', 'show_distance', 'show_online_status', 'allow_profile_views',
            ])->filter(fn ($v) => $v !== null)->all();
            if ($privacyAttrs) {
                ProfilePrivacy::updateOrCreate(['user_id' => $user->id], $privacyAttrs);
            }

            $completion = $user->profile()->first()?->completenessScore() ?? 0;
            $user->update(['profile_completion' => $completion]);
        });

        return response()->json(UserResource::make($user->fresh(['profile', 'photos', 'interests'])));
    }

    public function photos(Request $request, \App\Services\PhotoService $photos)
    {
        $request->validate([
            'photos' => ['required', 'array', 'max:9'],
            'photos.*' => ['file', 'max:8192'],
            'is_private' => ['sometimes', 'boolean'],
        ]);
        $stored = [];
        foreach ($request->file('photos', []) as $file) {
            try {
                $stored[] = $photos->upload($request->user(), $file, $request->boolean('is_private'));
            } catch (\InvalidArgumentException $e) {
                return $request->wantsJson()
                    ? response()->json(['message' => $e->getMessage()], 422)
                    : back()->withErrors(['photos' => $e->getMessage()]);
            }
        }

        return $request->wantsJson()
            ? response()->json($stored, 201)
            : back()->with('status', count($stored).' foto diupload, menunggu moderasi ✅');
    }

    public function destroyPhoto(Request $request, int $photo, \App\Services\PhotoService $photos)
    {
        $record = $request->user()->photos()->findOrFail($photo);
        $photos->destroy($request->user(), $record);

        return $request->wantsJson()
            ? response()->json(['message' => 'Photo deleted.'])
            : back()->with('status', 'Foto dihapus.');
    }

    public function video(Request $request)
    {
        $request->validate(['video' => ['required', 'file', 'max:51200', 'mimetypes:video/mp4,video/quicktime']]);
        $path = $request->file('video')->store('profile-videos', 'public');
        $record = $request->user()->videos()->create(['file_path' => $path]);

        return response()->json($record, 201);
    }

    public function privacy(Request $request)
    {
        $user = $request->user();
        $privacy = ProfilePrivacy::firstOrCreate(['user_id' => $user->id]);

        return response()->json($privacy);
    }
}
