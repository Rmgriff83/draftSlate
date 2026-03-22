<?php

use App\Jobs\CheckAndDispatchSlatePoolBuilds;
use App\Jobs\OddsRefreshJob;
use App\Jobs\ResultGradingJob;
use App\Jobs\SlateLockJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check for leagues needing slate pool builds (every 5 minutes)
Schedule::job(new CheckAndDispatchSlatePoolBuilds)->everyFiveMinutes();

// Lock picks for games at kickoff (every 5 minutes)
Schedule::job(new SlateLockJob)->everyFiveMinutes();

// Grade picks with result data (every 5 minutes)
Schedule::job(new ResultGradingJob)->everyFiveMinutes();

// Refresh odds for upcoming games (every 15 minutes)
Schedule::job(new OddsRefreshJob)->everyFifteenMinutes();
