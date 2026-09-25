<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Passport / travel mode (premium entitlement). When active, discovery
 * uses the virtual location instead of the real one. Exact GPS is never
 * exposed — only city-level labels reach other users.
 */
class PassportService
{
    public function active(User $user): bool
    {
        return (bool) $user->passport_active
            && $user->passport_latitude !== null
            && $user->passport_longitude !== null;
    }

    /** @return array{latitude:?float, longitude:?float, city:?string, label:string, is_passport:bool} */
    public function effectiveLocation(User $user): array
    {
        if ($this->active($user)) {
            return [
                'latitude' => (float) $user->passport_latitude,
                'longitude' => (float) $user->passport_longitude,
                'city' => $user->passport_city,
                'label' => ($user->passport_city ?? 'Lokasi virtual').' · mode Passport',
                'is_passport' => true,
            ];
        }

        return [
            'latitude' => $user->latitude !== null ? (float) $user->latitude : null,
            'longitude' => $user->longitude !== null ? (float) $user->longitude : null,
            'city' => $user->city,
            'label' => (string) ($user->city ?? 'Indonesia'),
            'is_passport' => false,
        ];
    }

    public function set(User $user, array $data): void
    {
        if (! $user->isPremium()) {
            throw new \RuntimeException('Passport adalah fitur Premium.');
        }
        $validated = validator($data, [
            'city' => ['required', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ])->validate();

        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'passport_city' => $validated['city'],
                'passport_province' => $validated['province'] ?? null,
                'passport_country' => $validated['country'] ?? 'Indonesia',
                'passport_latitude' => $validated['latitude'],
                'passport_longitude' => $validated['longitude'],
                'passport_active' => true,
            ]);
            app(AuditService::class)->log('passport.enabled', $user, $user, [], ['city' => $validated['city']]);
        });
    }

    public function clear(User $user): void
    {
        $was = $this->active($user);
        $user->update(['passport_active' => false]);
        if ($was) {
            try {
                app(AuditService::class)->log('passport.disabled', $user, $user);
            } catch (\Throwable) {
            }
        }
    }
}
