<?php

namespace App\Observers;

use App\Jobs\RecalculateMatches;
use App\Models\PartnerPreference;
use App\Models\Profile;
use App\Models\ProfilePhoto;
use App\Models\QuestionnaireAnswer;
use App\Models\User;
use App\Models\UserInterest;
use Illuminate\Support\Facades\Cache;

/**
 * Recalculate matches when match-relevant data actually changes.
 *
 * Replaces the old ProfileViewed listener (which spammed the queue on
 * every profile view with the wrong semantics). Only the owner's top
 * matches are refreshed, and their score/picks caches are busted.
 */
class MatchRecalcObserver
{
    public function saved(object $model): void
    {
        $this->handle($model);
    }

    public function deleted(object $model): void
    {
        $this->handle($model);
    }

    protected function handle(object $model): void
    {
        $userId = match (true) {
            $model instanceof User => (int) $model->id,
            $model instanceof Profile,
            $model instanceof PartnerPreference,
            $model instanceof ProfilePhoto,
            $model instanceof UserInterest,
            $model instanceof QuestionnaireAnswer => (int) $model->user_id,
            default => null,
        };
        if (! $userId) {
            return;
        }
        try {
            Cache::forget('discovery:picks:'.today()->toDateString().':'.$userId);
            RecalculateMatches::dispatch($userId, 30);
        } catch (\Throwable) {
        }
    }
}
