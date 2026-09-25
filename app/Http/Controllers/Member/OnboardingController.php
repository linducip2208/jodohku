<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Interest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Short guided onboarding: dasar → tujuan → foto → preferensi → discover.
 * Writes only to existing models (User/Profile/Interest/PartnerPreference/
 * ProfilePhoto via member.profile.photos). Steps are skippable; the home
 * banner re-invites members whose profile is still thin.
 */
class OnboardingController extends Controller
{
    public const STEPS = ['dasar', 'tujuan', 'foto', 'preferensi'];

    public function show(Request $request, string $step = 'dasar')
    {
        if (! in_array($step, self::STEPS, true)) {
            abort(404);
        }
        $user = $request->user()->load(['profile', 'interests', 'partnerPreference', 'photos']);
        $interests = Interest::where('is_active', true)->orderBy('name')->limit(40)->get();

        return view('member.onboarding.step', ['step' => $step, 'steps' => self::STEPS, 'user' => $user, 'interests' => $interests]);
    }

    public function store(Request $request, string $step)
    {
        if (! in_array($step, self::STEPS, true)) {
            abort(404);
        }
        $user = $request->user();

        match ($step) {
            'dasar' => $this->storeDasar($request, $user),
            'tujuan' => $this->storeTujuan($request, $user),
            'foto' => null, // photos upload to member.profile.photos directly
            'preferensi' => $this->storePreferensi($request, $user),
        };

        $next = self::STEPS[array_search($step, self::STEPS, true) + 1] ?? null;

        return redirect($next ? '/onboarding/'.$next : '/discover');
    }

    protected function storeDasar(Request $request, $user): void
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:60'],
            'date_of_birth' => ['required', 'date', 'before:'.now()->subYears(17)->toDateString()],
            'gender' => ['required', 'string', 'in:male,female'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);
        $user->update($data);
    }

    protected function storeTujuan(Request $request, $user): void
    {
        $data = $request->validate([
            'relationship_goal' => ['nullable', 'string', 'in:marriage,serious_relationship,dating,friendship,casual,networking,undecided'],
            'interests' => ['nullable', 'array', 'max:10'],
            'interests.*' => ['integer', 'exists:interests,id'],
        ]);
        DB::transaction(function () use ($user, $data) {
            if (! empty($data['relationship_goal'])) {
                $user->profile()->updateOrCreate([], ['relationship_goal' => $data['relationship_goal']]);
            }
            if (isset($data['interests'])) {
                $user->interests()->sync($data['interests']);
            }
        });
    }

    protected function storePreferensi(Request $request, $user): void
    {
        $data = $request->validate([
            'gender_preference' => ['nullable', 'string', 'in:male,female'],
            'min_age' => ['nullable', 'integer', 'min:17', 'max:80'],
            'max_age' => ['nullable', 'integer', 'min:17', 'max:80'],
            'max_distance_km' => ['nullable', 'integer', 'min:1', 'max:20000'],
        ]);
        $filtered = array_filter($data, fn ($v) => $v !== null && $v !== '');
        if ($filtered) {
            $user->partnerPreference()->updateOrCreate([], $filtered);
        }
    }

    public static function needsOnboarding($user): bool
    {
        if (! $user) {
            return false;
        }
        try {
            if ((int) ($user->profile_completion ?? 0) < 60) {
                return true;
            }

            return ! $user->photos()->where('status', 'approved')->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
