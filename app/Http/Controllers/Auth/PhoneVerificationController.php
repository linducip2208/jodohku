<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class PhoneVerificationController extends Controller
{
    public function send(Request $request)
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $phone = (string) $request->input('phone');
        // Per-IP + per-phone throttling (SMS cost + brute-force protection).
        foreach (['phone-otp-send:'.$request->ip(), 'phone-otp-send-phone:'.sha1($phone)] as $key) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return $request->wantsJson()
                    ? response()->json(['message' => 'Too many attempts.'], 429)
                    : back()->withErrors(['phone' => 'Terlalu sering. Coba lagi 5 menit.']);
            }
            RateLimiter::hit($key, 300);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // Never store raw OTP: HMAC with app key, verify with hash_equals.
        $hash = hash_hmac('sha256', $otp, (string) config('app.key'));
        Cache::put('phone-otp:'.sha1($phone), $hash, now()->addMinutes(10));

        // SMS gateway integration point: dispatch via Notification if configured.
        return $request->wantsJson()
            ? response()->json(['message' => 'OTP sent.'])
            : back()->with('status', 'Kode OTP dikirim. Berlaku 10 menit.');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'otp' => ['required', 'string', 'size:6'],
        ]);
        if (! $request->user()) {
            abort(401);
        }
        $phone = (string) $request->input('phone');
        $verifyKey = 'phone-otp-verify:'.$request->ip().':'.sha1($phone);
        if (RateLimiter::tooManyAttempts($verifyKey, 5)) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Too many attempts.'], 429)
                : back()->withErrors(['code' => 'Terlalu sering. Coba lagi 5 menit.']);
        }
        RateLimiter::hit($verifyKey, 300);

        $expected = Cache::get('phone-otp:'.sha1($phone));
        $candidate = hash_hmac('sha256', (string) $request->input('otp'), (string) config('app.key'));
        if (! $expected || ! hash_equals((string) $expected, $candidate)) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Invalid OTP.'], 422)
                : back()->withErrors(['code' => 'Kode salah atau kedaluwarsa.']);
        }
        Cache::forget('phone-otp:'.sha1($phone));
        RateLimiter::clear($verifyKey);
        $request->user()->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();

        return $request->wantsJson()
            ? response()->json(['message' => 'Phone verified.'])
            : redirect('/home')->with('status', 'Nomor terverifikasi ✅');
    }
}
