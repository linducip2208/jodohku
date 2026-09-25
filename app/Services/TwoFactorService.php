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
        $user->update(['two_factor_enabled' => false, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_backup_codes' => null]);
        Cache::forget($this->key($user));
        Cache::forget($this->pendingKey($user));
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
        if (! $this->isEnabled($user)) {
            return false;
        }
        if (RateLimiter::tooManyAttempts('2fa-verify:'.$user->id, 5)) {
            throw new \RuntimeException('Terlalu banyak percobaan. Coba lagi dalam 5 menit.');
        }
        // Authenticator TOTP wins when a confirmed secret exists.
        if ($user->two_factor_secret) {
            try {
                $secret = decrypt($user->two_factor_secret);
            } catch (\Throwable) {
                $secret = null;
            }
            if (is_string($secret) && $this->verifyTotpCode($secret, trim($code))) {
                RateLimiter::clear('2fa-verify:'.$user->id);
                $user->update(['two_factor_confirmed_at' => now()]);
                $this->audit->log('2fa.verified', $user, $user, [], ['method' => 'totp']);

                return true;
            }
            // Single-use backup codes (hashed at rest).
            if ($this->consumeBackupCode($user, trim($code))) {
                RateLimiter::clear('2fa-verify:'.$user->id);
                $this->audit->log('2fa.verified', $user, $user, [], ['method' => 'backup_code']);

                return true;
            }
        }
        $hash = Cache::get($this->key($user));
        if (! $hash || ! Hash::check(trim($code), $hash)) {
            RateLimiter::hit('2fa-verify:'.$user->id, 300);

            return false;
        }
        RateLimiter::clear('2fa-verify:'.$user->id);
        Cache::forget($this->key($user));
        $user->update(['two_factor_confirmed_at' => now()]);
        $this->audit->log('2fa.verified', $user, $user, [], ['method' => 'email_otp']);

        return true;
    }

    // ---------- TOTP authenticator (RFC 6238, SHA1, 30s, 6 digit, no deps) ----------

    /** Start authenticator setup: returns secret + otpauth URL (valid 15 min). */
    public function startTotpSetup(User $user): array
    {
        $secret = $this->generateSecret();
        Cache::put($this->pendingKey($user), encrypt($secret), now()->addMinutes(15));
        $issuer = rawurlencode((string) config('app.name', 'Jodohku'));
        $label = rawurlencode((string) ($user->email ?? 'user'));
        $this->audit->log('2fa.totp_setup_started', $user, $user);

        return [
            'secret' => $secret,
            'otpauth_url' => "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30",
        ];
    }

    /** Confirm setup with a current code; issues backup codes on success. */
    public function confirmTotpSetup(User $user, string $code): array
    {
        $pending = Cache::get($this->pendingKey($user));
        if (! $pending) {
            throw new \RuntimeException('Setup kedaluwarsa. Mulai ulang.');
        }
        try {
            $secret = decrypt($pending);
        } catch (\Throwable) {
            throw new \RuntimeException('Setup rusak. Mulai ulang.');
        }
        if (! $this->verifyTotpCode($secret, trim($code))) {
            throw new \RuntimeException('Kode salah. Cek jam authenticator.');
        }
        $codes = $this->freshBackupCodes();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_backup_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ]);
        Cache::forget($this->pendingKey($user));
        $this->audit->log('2fa.totp_enabled', $user, $user);

        return $codes;
    }

    /** Regenerate backup codes (old ones die). Returns plaintext (show once). */
    public function regenerateBackupCodes(User $user): array
    {
        $codes = $this->freshBackupCodes();
        $user->update(['two_factor_backup_codes' => array_map(fn ($c) => Hash::make($c), $codes)]);
        $this->audit->log('2fa.backup_regenerated', $user, $user);

        return $codes;
    }

    public function hasTotp(User $user): bool
    {
        return $user->two_factor_secret !== null;
    }

    public function totpCodeFor(string $secret, ?int $at = null): string
    {
        return $this->totp($secret, $at ?? time());
    }

    protected function verifyTotpCode(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $t = time();
        foreach ([-1, 0, 1] as $drift) {
            if (hash_equals($this->totp($secret, $t + $drift * 30), $code)) {
                return true;
            }
        }

        return false;
    }

    protected function totp(string $secret, int $at): string
    {
        $key = $this->base32Decode($secret);
        $counter = (int) floor($at / 30);
        $msg = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $msg, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = ((ord($hash[$offset]) & 0x7F) << 24 | ord($hash[$offset + 1]) << 16 | ord($hash[$offset + 2]) << 8 | ord($hash[$offset + 3])) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    protected function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    protected function freshBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        }

        return $codes;
    }

    protected function consumeBackupCode(User $user, string $code): bool
    {
        $hashes = $user->two_factor_backup_codes ?? [];
        if (! is_array($hashes) || $hashes === []) {
            return false;
        }
        foreach ($hashes as $i => $hash) {
            if (is_string($hash) && Hash::check($code, $hash)) {
                unset($hashes[$i]);
                $user->update(['two_factor_backup_codes' => array_values($hashes)]);

                return true;
            }
        }

        return false;
    }

    protected function base32Encode(string $raw): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($raw) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    protected function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(rtrim($b32, '='));
        $bits = '';
        foreach (str_split($b32) as $ch) {
            $pos = strpos($alphabet, $ch);
            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid base32.');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    protected function pendingKey(User $user): string
    {
        return '2fa:totp:pending:'.$user->id;
    }

    protected function key(User $user): string
    {
        return '2fa:challenge:'.$user->id;
    }
}
