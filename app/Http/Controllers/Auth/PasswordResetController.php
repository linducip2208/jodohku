<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function request(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Password reset endpoint.']);
        }

        return view('auth.forgot-password');
    }

    public function send(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        if ($request->wantsJson()) {
            return $status === Password::RESET_LINK_SENT
                ? response()->json(['message' => 'Reset link sent.'])
                : response()->json(['message' => 'Unable to send reset link.'], 422);
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'Reset link sent.')
            : back()->withErrors(['email' => 'Unable to send reset link.']);
    }

    public function reset(Request $request, string $token)
    {
        if ($request->wantsJson()) {
            return response()->json(['token' => $token]);
        }

        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->forceFill(['password' => $password, 'remember_token' => \Illuminate\Support\Str::random(60)])->save();
        });

        if ($request->wantsJson()) {
            return $status === Password::PASSWORD_RESET
                ? response()->json(['message' => 'Password reset.'])
                : response()->json(['message' => 'Invalid token.'], 422);
        }

        return $status === Password::PASSWORD_RESET
            ? redirect('/login')->with('status', 'Password reset.')
            : back()->withErrors(['email' => 'Invalid token.']);
    }
}
