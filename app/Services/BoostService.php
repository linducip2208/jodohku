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

        return DB::transaction(function () use ($user, $durationMinutes) {
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
}
