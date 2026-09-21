<?php

use App\Jobs\CleanupOldSessions;
use App\Jobs\ExpireBoost;
use App\Jobs\ExpireCredits;
use App\Jobs\ExpireSubscriptions;
use App\Jobs\GenerateDailyMatches;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateDailyMatches)->dailyAt('01:00');
Schedule::job(new ExpireSubscriptions)->hourly();
Schedule::job(new ExpireCredits)->hourly();
Schedule::job(new ExpireBoost)->hourly();
Schedule::job(new CleanupOldSessions)->dailyAt('03:00');
