<?php

use App\Jobs\CleanupOldSessions;
use App\Jobs\DispatchScheduledMessages;
use App\Jobs\ExpireBoost;
use App\Jobs\ExpireCredits;
use App\Jobs\ExpireRingingCalls;
use App\Jobs\ExpireSubscriptions;
use App\Jobs\ExpireVerifications;
use App\Jobs\GenerateDailyMatches;
use App\Jobs\PruneDisappearingMessages;
use App\Jobs\PruneStaleData;
use App\Jobs\SendMatchReminders;
use App\Jobs\SendTaarufReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateDailyMatches)->dailyAt('01:00');
Schedule::job(new DispatchScheduledMessages)->everyMinute();
Schedule::job(new ExpireRingingCalls)->everyMinute();
Schedule::job(new PruneDisappearingMessages)->hourly();
Schedule::job(new ExpireSubscriptions)->hourly();
Schedule::job(new ExpireCredits)->hourly();
Schedule::job(new ExpireBoost)->hourly();
Schedule::job(new ExpireVerifications)->dailyAt('02:00');
Schedule::job(new CleanupOldSessions)->dailyAt('03:00');
Schedule::job(new PruneStaleData)->dailyAt('03:30');
Schedule::job(new SendMatchReminders)->weeklyOn(1, '09:00');
Schedule::job(new SendTaarufReminders)->weeklyOn(4, '09:00');
