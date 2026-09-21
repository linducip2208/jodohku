<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\User;

class AdService
{
    public function impression(Ad $ad, ?User $user = null, string $placement = 'feed'): void
    {
        $ad->impressions()->create([
            'user_id' => $user?->id,
            'placement' => $placement,
            'ip_address' => request()->ip(),
        ]);
        $ad->increment('impressions_count');
    }

    public function click(Ad $ad, ?User $user = null, string $placement = 'feed'): void
    {
        $ad->clicks()->create([
            'user_id' => $user?->id,
            'placement' => $placement,
            'ip_address' => request()->ip(),
        ]);
        $ad->increment('clicks_count');
    }

    public function servable(string $placement = 'feed', int $limit = 5)
    {
        return Ad::servable()->where('placement', $placement)->limit($limit)->get();
    }
}
