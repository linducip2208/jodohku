<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Modular commercial license check.
 *
 * Disabled by default (LICENSE_ENABLED=false): every install — dev, test,
 * self-hosted — runs fully licensed. When enabled, validates key format
 * (XXXX-XXXX-XXXX-XXXX), domain binding, and expiry locally, with an
 * optional remote activation endpoint (LICENSE_VERIFY_URL) cached 6h so a
 * vendor outage never bricks a licensed install (fail-open on network
 * error only when a previous successful verification exists).
 */
class LicenseManager
{
    public function enabled(): bool
    {
        return (bool) config('license.enabled', false);
    }

    /** Full status array for the license:status command + admin health. */
    public function status(): array
    {
        if (! $this->enabled()) {
            return ['mode' => 'disabled (development)', 'licensed' => true, 'reason' => 'License gate is off.'];
        }
        $key = trim((string) config('license.key', ''));
        if (! preg_match('/^[A-Z0-9]{4}(-[A-Z0-9]{4}){3}$/i', $key)) {
            return ['mode' => 'enforced', 'licensed' => false, 'reason' => 'LICENSE_KEY missing or malformed.'];
        }
        $domain = trim((string) config('license.domain', ''));
        if ($domain !== '' && request()?->getHost() && strtolower(request()->getHost()) !== strtolower($domain)) {
            return ['mode' => 'enforced', 'licensed' => false, 'reason' => 'Domain mismatch.'];
        }
        $exp = trim((string) config('license.expires_at', ''));
        if ($exp !== '' && now()->gt($exp)) {
            return ['mode' => 'enforced', 'licensed' => false, 'reason' => 'License expired on '.$exp.'.'];
        }
        $remote = $this->remoteStatus($key);
        if ($remote !== null && $remote === false) {
            return ['mode' => 'enforced', 'licensed' => false, 'reason' => 'Vendor activation revoked this key.'];
        }

        return [
            'mode' => 'enforced', 'licensed' => true, 'reason' => 'Valid.',
            'support_until' => config('license.support_until') ?: null,
            'remote_verified' => $remote,
        ];
    }

    public function licensed(): bool
    {
        return (bool) ($this->status()['licensed'] ?? false);
    }

    /**
     * Optional remote check. Returns null when unconfigured (local-only
     * mode), true/false on verified response. Cached 6h; network errors
     * return the last known value (fail-open) to survive vendor outages.
     */
    protected function remoteStatus(string $key): ?bool
    {
        $url = trim((string) config('license.verify_url', ''));
        if ($url === '') {
            return null;
        }
        $cacheKey = 'license:remote:'.sha1($key);
        try {
            $res = Http::timeout((int) config('license.verify_timeout', 10))
                ->post($url, ['key' => $key, 'domain' => request()?->getHost(), 'version' => config('app.version', '1.0.0')]);
            if ($res->successful()) {
                $ok = (bool) ($res->json('active', $res->json('licensed', false)));
                Cache::put($cacheKey, $ok, now()->addHours(6));

                return $ok;
            }
        } catch (\Throwable) {
        }

        return Cache::get($cacheKey);
    }
}
