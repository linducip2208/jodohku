<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/home');
        }
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Email not verified.'], 403);
        }

        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Email verified.']);
        }

        return redirect()->intended('/home?verified=1');
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Already verified.'])
                : redirect()->intended('/home');
        }
        $request->user()->sendEmailVerificationNotification();

        return $request->wantsJson()
            ? response()->json(['message' => 'Verification link sent.'])
            : back()->with('status', 'Verification link sent.');
    }
}
