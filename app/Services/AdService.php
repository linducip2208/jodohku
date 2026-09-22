<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\User;

class AdService
{
    public function isServable(Ad $ad): bool
    {
        return Ad::servable()->where('ads.id', $ad->id)->exists();
    }

    public function impression(Ad $ad, ?User $user = null, string $placement = 'feed'): void
    {
        if (! $this->isServable($ad)) {
            return;
        }
        $ad->impressions()->create([
            'user_id' => $user?->id,
            'placement' => $placement,
            'ip_address' => request()->ip(),
        ]);
        $ad->increment('impressions_count');
    }

    public function click(Ad $ad, ?User $user = null, string $placement = 'feed'): void
    {
        if (! $this->isServable($ad)) {
            return;
        }
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

    /** Aggregate delivery stats per placement from the counter columns. */
    public function stats(?string $placement = null): array
    {
        return Ad::when($placement, fn ($q) => $q->where('placement', $placement))
            ->selectRaw('placement, COUNT(*) as ads, SUM(impressions_count) as impressions, SUM(clicks_count) as clicks')
            ->groupBy('placement')->get()
            ->map(fn ($r) => [
                'placement' => $r->placement,
                'ads' => (int) $r->ads,
                'impressions' => (int) $r->impressions,
                'clicks' => (int) $r->clicks,
                'ctr_pct' => $r->impressions > 0 ? round($r->clicks / $r->impressions * 100, 2) : 0.0,
            ])->all();
    }
}
