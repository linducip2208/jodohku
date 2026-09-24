<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DiscoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * scale:probe — recreate the SCALE-AUDIT perf numbers on demand.
 *
 * Measures ms + query counts for the hot read paths on the CURRENT dataset
 * (discover p1, filtered discover, dailyPicks, notification count) plus row
 * counts for the unbounded tables (C3 watchlist). Default wraps everything
 * in a rolled-back transaction so probing never writes; --persist keeps the
 * warmed match_scores (useful right before peak hours).
 *
 * Usage: php artisan scale:probe [--persist] [--user=ID]
 */
class ScaleProbe extends Command
{
    protected $signature = 'scale:probe {--persist : keep warmed match_scores instead of rolling back} {--user= : probe as a specific user id}';

    protected $description = 'Measure hot-path ms/queries + unbounded table sizes (scale ops)';

    public function handle(DiscoveryService $discovery): int
    {
        $persist = (bool) $this->option('persist');
        $user = $this->option('user')
            ? User::find($this->option('user'))
            : User::active()->orderBy('id')->first();
        if (! $user) {
            $this->error('No active user to probe as.');

            return self::FAILURE;
        }

        $watch = ['notifications', 'audit_logs', 'jobs', 'failed_jobs', 'match_scores', 'ai_usage_logs', 'profile_views'];
        $sizes = [];
        foreach ($watch as $table) {
            try {
                $sizes[$table] = DB::table($table)->count();
            } catch (\Throwable) {
                $sizes[$table] = 'n/a';
            }
        }

        DB::beginTransaction();
        try {
            $p1 = $this->measure(fn () => $discovery->discover($user, [], 20));
            $filtered = $this->measure(fn () => $discovery->discover($user, [
                'gender' => $user->gender?->value ?? 'female',
                'city' => (string) $user->city,
                'verified' => true,
            ], 20));
            $picks = $this->measure(function () use ($discovery, $user) {
                try {
                    return $discovery->dailyPicks($user, 10);
                } finally {
                    $discovery->resetDailyPicks($user);
                }
            });
            $notif = $this->measure(fn () => DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->whereNull('read_at')->count());
        } finally {
            if ($persist) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        }

        // Cache driver visibility (database cache on MySQL = C1 pressure).
        $cacheDriver = (string) config('cache.default');
        $queueDriver = (string) config('queue.default');

        $this->table(
            ['probe (users: '.number_format(User::count()).', as #'.$user->id.', '.($persist ? 'persisted' : 'rolled back').')', 'ms', 'queries'],
            [
                ['discover p1', $p1['ms'], $p1['queries']],
                ['discover filtered', $filtered['ms'], $filtered['queries']],
                ['dailyPicks', $picks['ms'], $picks['queries']],
                ['notifications unread', $notif['ms'], $notif['queries']],
            ]
        );
        $this->table(['unbounded table', 'rows'], collect($sizes)->map(fn ($v, $k) => [$k, is_int($v) ? number_format($v) : $v])->values()->all());
        $this->line("cache={$cacheDriver} queue={$queueDriver} (want redis/redis in production)");

        return self::SUCCESS;
    }

    /** @return array{ms:int,queries:int} */
    protected function measure(callable $fn): array
    {
        // No Cache::flush(): probe measures the realistic path (mix of hits
        // and misses), and flushing would nuke other users' cached picks.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $t = microtime(true);
        try {
            $fn();
        } finally {
            $ms = (int) round((microtime(true) - $t) * 1000);
            $queries = count(DB::getQueryLog());
            DB::flushQueryLog();
        }

        return ['ms' => $ms, 'queries' => $queries];
    }
}
