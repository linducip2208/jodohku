<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\TwoFactorCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorService
{
    protected int $ttlMinutes = 10;

    public function __construct(protected AuditService $audit) {}

    public function isEnabled(User $user): bool
    {
        return (bool) $user->two_factor_enabled;
    }

    public function enable(User $user): void
    {
        $user->update(['two_factor_enabled' => true, 'two_factor_confirmed_at' => null]);
        $this->audit->log('2fa.enabled', $user, $user);
    }

    public function disable(User $user): void
    {
        $user->update(['two_factor_enabled' => false, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
        Cache::forget($this->key($user));
        $this->audit->log('2fa.disabled', $user, $user);
    }

    /** Generate + mail a one-time code. Throttled to one send per 60s per user. */
    public function sendChallenge(User $user): void
    {
        $throttleKey = '2fa-send:'.$user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            throw new \RuntimeException('Kode baru saja dikirim. Tunggu '.RateLimiter::availableIn($throttleKey).' detik.');
        }
        RateLimiter::hit($throttleKey, 60);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->key($user), Hash::make($code), now()->addMinutes($this->ttlMinutes));
        $user->notify(new TwoFactorCode($code, $this->ttlMinutes));
        // Never log the code itself.
        $this->audit->log('2fa.challenge_sent', $user, $user);
    }

    public function verify(User $user, string $code): bool
    {
        $hash = Cache::get($this->key($user));
        if (! $hash || ! Hash::check(trim($code), $hash)) {
            RateLimiter::hit('2fa-verify:'.$user->id, 300);
            if (RateLimiter::tooManyAttempts('2fa-verify:'.$user->id, 5)) {
                throw new \RuntimeException('Terlalu banyak percobaan. Coba lagi dalam 5 menit.');
            }

            return false;
        }
        RateLimiter::clear('2fa-verify:'.$user->id);
        Cache::forget($this->key($user));
        $user->update(['two_factor_confirmed_at' => now()]);
        $this->audit->log('2fa.verified', $user, $user);

        return true;
    }

    protected function key(User $user): string
    {
        return '2fa:challenge:'.$user->id;
    }
}
