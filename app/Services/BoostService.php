<?php

namespace App\Services;

use App\Enums\BoostStatus;
use App\Models\Boost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BoostService
{
    /** Activate boost: presentation priority only, never changes compatibility score. */
    public function activate(User $user, int $durationMinutes = 30): Boost
    {
        if (! config('jodohku.features.boost', true)) {
            throw new \RuntimeException('Boost feature disabled.');
        }
        try {
            if (! app(BrandService::class)->featureEnabled('boost')) {
                throw new \RuntimeException('Boost feature disabled.');
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
        }

        return DB::transaction(function () use ($user, $durationMinutes) {
            // Serialize concurrent activations for the same user.
            Boost::where('user_id', $user->id)->lockForUpdate()->exists();
            Boost::where('user_id', $user->id)->where('status', BoostStatus::Active->value)
                ->update(['status' => BoostStatus::Expired->value]);
            $boost = Boost::create([
                'user_id' => $user->id,
                'status' => BoostStatus::Active,
                'duration_minutes' => $durationMinutes,
                'starts_at' => now(),
                'ends_at' => now()->copy()->addMinutes($durationMinutes),
            ]);

            return $boost;
        });
    }

    public function expire(Boost $boost): bool
    {
        return $boost->update(['status' => BoostStatus::Expired]);
    }

    public function expireDue(int $batch = 200): int
    {
        // Chunk by ids instead of UPDATE..LIMIT (SQLite-incompatible).
        $ids = Boost::where('status', BoostStatus::Active->value)
            ->where('ends_at', '<=', now())->limit($batch)->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        return Boost::whereIn('id', $ids)->update(['status' => BoostStatus::Expired->value]);
    }

    public function isLive(User $user): bool
    {
        return Boost::where('user_id', $user->id)->live()->exists();
    }

    /** Rich live status: active boost with remaining time, if any. */
    public function status(User $user): array
    {
        $active = Boost::where('user_id', $user->id)->live()->latest('id')->first();

        return [
            'live' => $active !== null,
            'boost_id' => $active?->id,
            'starts_at' => $active?->starts_at,
            'ends_at' => $active?->ends_at,
            'remaining_seconds' => $active && $active->ends_at ? max(0, now()->diffInSeconds($active->ends_at, false)) : 0,
        ];
    }

    public function history(User $user, int $perPage = 20)
    {
        return Boost::where('user_id', $user->id)->latest('id')->paginate($perPage);
    }
}
