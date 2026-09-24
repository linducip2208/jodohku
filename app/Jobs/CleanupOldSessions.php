<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class CleanupOldSessions implements ShouldQueue
{
    use Queueable, Concerns\HasScaleLimits;

    public $tries = 3;

    public $timeout = 300;

    public function handle(): void
    {
        DB::table('sessions')->where('last_activity', '<', now()->subDays(30)->timestamp)->delete();
        DB::table('cache')->where('expiration', '<', now()->timestamp)->delete();
    }
}
