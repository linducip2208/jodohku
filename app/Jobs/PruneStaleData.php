<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Retention/pruning for unbounded tables (SCALE-AUDIT P1).
 *
 * Without this, notifications/audit_logs/failed_jobs/match_scores/
 * ai_usage_logs/profile_views grow forever on one MySQL instance (C3).
 * All deletes are chunked (1k rows) and time-bounded so the nightly job
 * never holds long locks. Thresholds are conservative — read notifications
 * survive 90d, everything else 30d–365d — and re-derivable data
 * (match_scores) is pruned most aggressively since scoreMany recomputes
 * on demand.
 */
class PruneStaleData implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 3;

    public $timeout = 600;

    public function handle(): void
    {
        $this->chunkDelete('notifications', fn ($q) => $q->whereNotNull('read_at')->where('read_at', '<', now()->subDays(90)));
        $this->chunkDelete('notifications', fn ($q) => $q->where('created_at', '<', now()->subDays(365)));
        $this->chunkDelete('audit_logs', fn ($q) => $q->where('created_at', '<', now()->subDays(180)));
        $this->chunkDelete('failed_jobs', fn ($q) => $q->where('failed_at', '<', now()->subDays(30)));
        $this->chunkDelete('ai_usage_logs', fn ($q) => $q->where('created_at', '<', now()->subDays(365)));
        $this->chunkDelete('profile_views', fn ($q) => $q->where('viewed_at', '<', now()->subDays(180)));
        $this->chunkDelete('match_scores', fn ($q) => $q->where('computed_at', '<', now()->subDays(90)));

        // Database queue driver only: stuck rows (unix timestamps) older
        // than 7 days are poison/orphaned — successful jobs self-delete.
        try {
            $cutoff = now()->subDays(7)->timestamp;
            do {
                $n = DB::table('jobs')->where('created_at', '<', $cutoff)->limit(1000)->delete();
            } while ($n > 0);
        } catch (\Throwable) {
        }

        // Finished job batches (integer timestamps) older than 30 days.
        try {
            $cutoff = now()->subDays(30)->timestamp;
            do {
                $n = DB::table('job_batches')->whereNotNull('finished_at')->where('finished_at', '<', $cutoff)->limit(1000)->delete();
            } while ($n > 0);
        } catch (\Throwable) {
        }
    }

    /** @param callable(Builder):void $scope */
    protected function chunkDelete(string $table, callable $scope): void
    {
        try {
            do {
                $query = DB::table($table);
                $scope($query);
                $n = $query->limit(1000)->delete();
            } while ($n > 0);
        } catch (\Throwable) {
        }
    }
}
