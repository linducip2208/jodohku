<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Block;
use App\Models\Report;
use App\Services\AdService;
use App\Services\TwoFactorService;
use App\Services\VerificationService;
use Illuminate\Http\Request;

class SafetyController extends Controller
{
    public function index(Request $request, VerificationService $verification, TwoFactorService $tfa)
    {
        $user = $request->user();
        $status = $user ? [
            'email_verified' => (bool) $user->hasVerifiedEmail(),
            'is_verified' => (bool) $user->is_verified,
            'two_factor' => $tfa->isEnabled($user),
            'incognito' => (bool) ($user->profilePrivacy?->is_incognito ?? false),
            'verification_requests' => $verification->statusFor($user),
            'blocks_count' => Block::where('blocker_id', $user->id)->count(),
            'reports_count' => Report::where('reporter_id', $user->id)->count(),
        ] : null;
        $payload = [
            'tips' => config('jodohku.safety_tips', []),
            'support_email' => config('mail.support', 'support@jodohku.id'),
            'status' => $status,
        ];

        return $request->wantsJson()
            ? response()->json($payload)
            : view('member.safety.center', ['safetyStatus' => $status]);
    }

    public function ads(Request $request, AdService $ads)
    {
        $request->validate(['placement' => ['nullable', 'string', 'max:60']]);
        $items = $ads->servable($request->input('placement', 'feed'));

        return response()->json($items);
    }

    public function adImpression(Request $request, Ad $ad, AdService $ads)
    {
        $ads->impression($ad, $request->user(), $request->input('placement', 'feed'));

        return response()->json(['ok' => true]);
    }

    public function adClick(Request $request, Ad $ad, AdService $ads)
    {
        $ads->click($ad, $request->user(), $request->input('placement', 'feed'));

        return response()->json(['ok' => true]);
    }

    public function adStats(Request $request, AdService $ads)
    {
        $request->validate(['placement' => ['nullable', 'string', 'max:60']]);

        return response()->json($ads->stats($request->input('placement')));
    }
}
