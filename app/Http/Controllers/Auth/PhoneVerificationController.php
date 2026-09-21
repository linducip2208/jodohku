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
        $key = 'phone-otp-send:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Too many attempts.'], 429)
                : back()->withErrors(['phone' => 'Terlalu sering. Coba lagi 5 menit.']);
        }
        RateLimiter::hit($key, 300);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put('phone-otp:'.$request->input('phone'), $otp, now()->addMinutes(10));

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
        $expected = Cache::get('phone-otp:'.$request->input('phone'));
        if (! $expected || ! hash_equals((string) $expected, (string) $request->input('otp'))) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Invalid OTP.'], 422)
                : back()->withErrors(['code' => 'Kode salah atau kedaluwarsa.']);
        }
        Cache::forget('phone-otp:'.$request->input('phone'));
        $request->user()->forceFill(['phone' => $request->input('phone'), 'phone_verified_at' => now()])->save();

        return $request->wantsJson()
            ? response()->json(['message' => 'Phone verified.'])
            : redirect('/home')->with('status', 'Nomor terverifikasi ✅');
    }
}
