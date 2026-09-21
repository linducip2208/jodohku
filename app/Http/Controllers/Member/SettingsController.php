<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load(['notificationPreference', 'profilePrivacy']);

        return $request->wantsJson()
            ? response()->json($user)
            : view('member.settings.index', ['user' => $user]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email,'.$request->user()->id],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $request->user()->update($request->only(['name', 'email', 'phone']));

        return response()->json($request->user()->fresh());
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::min(8), 'confirmed'],
        ]);
        $request->user()->update(['password' => Hash::make($request->string('password'))]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        auth()->logout();
        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }

    public function enable2fa(Request $request, \App\Services\TwoFactorService $tfa)
    {
        $user = $request->user();
        $tfa->enable($user);
        try {
            $tfa->sendChallenge($user);
        } catch (\RuntimeException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 429)
                : back()->with('status', $e->getMessage());
        }

        return $request->wantsJson()
            ? response()->json(['message' => '2FA enabled. Verification code sent to your email.'])
            : back()->with('status', '2FA aktif. Kode verifikasi dikirim ke email ✅');
    }

    public function disable2fa(Request $request, \App\Services\TwoFactorService $tfa)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $tfa->disable($request->user());

        return $request->wantsJson()
            ? response()->json(['message' => '2FA disabled.'])
            : back()->with('status', '2FA dinonaktifkan.');
    }
}
