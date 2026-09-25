<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Profile;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $u = User::create([
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'phone' => $request->input('phone'),
                'password' => Hash::make($request->string('password')),
                'date_of_birth' => $request->date('date_of_birth'),
                'gender' => $request->input('gender'),
                'city' => $request->input('city'),
            ]);
            Profile::firstOrCreate(['user_id' => $u->id]);
            $u->partnerPreference()->firstOrCreate([]);
            $u->creditWallet()->firstOrCreate([], ['balance' => 0]);

            return $u;
        });
        event(new UserRegistered($user));
        $token = $user->createToken('api')->plainTextToken;
        // Serialization uses the base container request, not the FormRequest,
        // so bind the new user on both for privacy-aware resources.
        $request->setUserResolver(fn () => $user);
        request()->setUserResolver(fn () => $user);

        return response()->json(['user' => UserResource::make($user->fresh()), 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required_without:email', 'nullable', 'string'],
            'email' => ['required_without:login', 'nullable', 'string'],
            'password' => ['required', 'string'],
        ]);
        $login = (string) ($data['login'] ?? $data['email']);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : (is_numeric($login) || str_starts_with($login, '+') ? 'phone' : 'username');

        // Stateless verification (default guard may be sanctum/RequestGuard
        // which has no attempt() method).
        $candidate = User::where($field, $login)->first();
        if (! $candidate || ! Hash::check((string) $request->string('password'), (string) $candidate->password)) {
            throw ValidationException::withMessages(['login' => 'Invalid credentials.', 'email' => 'Invalid credentials.']);
        }
        $user = $candidate;
        if ($reason = $user->loginBlockedReason()) {
            return response()->json(['message' => $reason], 403);
        }
        if ($user->two_factor_enabled) {
            try {
                app(TwoFactorService::class)->sendChallenge($user);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 429);
            }

            return response()->json(['two_factor_required' => true, 'user_id' => $user->id]);
        }
        $user->update(['is_online' => true, 'last_active_at' => now()]);
        $token = $user->createToken('api')->plainTextToken;

        return response()->json(['user' => UserResource::make($user->fresh()), 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function verify2fa(Request $request)
    {
        $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'code' => ['required', 'string', 'max:6']]);
        // Per-user+IP brute-force guard (user_id is enumerable by design for
        // the 2FA step, so rate-limit aggressively per account + IP).
        $key = 'auth-2fa:'.(int) $request->integer('user_id').':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many attempts.'], 429);
        }
        RateLimiter::hit($key, 300);
        $user = User::findOrFail($request->integer('user_id'));
        if ($reason = $user->loginBlockedReason()) {
            return response()->json(['message' => $reason], 403);
        }
        $tfa = app(TwoFactorService::class);
        try {
            $ok = $tfa->verify($user, (string) $request->input('code'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }
        if (! $ok) {
            return response()->json(['message' => 'Invalid code.'], 422);
        }
        RateLimiter::clear($key);
        $user->update(['is_online' => true, 'last_active_at' => now()]);
        $token = $user->createToken('api')->plainTextToken;

        return response()->json(['user' => UserResource::make($user->fresh()), 'token' => $token]);
    }

    public function me(Request $request)
    {
        return response()->json(UserResource::make($request->user()->load(['profile', 'photos', 'interests'])));
    }
}
