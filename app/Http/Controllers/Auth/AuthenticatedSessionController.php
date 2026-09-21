<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Login endpoint.']);
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $this->ensureNotThrottled($request);

        $login = $request->string('login')->toString();
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : (str_starts_with($login, '+') || is_numeric($login) ? 'phone' : 'username');

        if (! Auth::attempt([$field => $login, 'password' => $request->string('password')], $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));
            throw ValidationException::withMessages(['login' => 'Invalid credentials.']);
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();
        $request->user()->update(['is_online' => true, 'last_active_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Logged in.', 'user_id' => $request->user()->id]);
        }

        return redirect()->intended('/app');
    }

    public function destroy(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $request->user()?->update(['is_online' => false]);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Logged out.']);
        }

        return redirect('/');
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('login')->toString()).'|'.$request->ip());
    }

    protected function ensureNotThrottled(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }
        $seconds = RateLimiter::availableIn($this->throttleKey($request));
        throw ValidationException::withMessages(['login' => "Too many attempts. Try again in {$seconds}s."]);
    }
}
