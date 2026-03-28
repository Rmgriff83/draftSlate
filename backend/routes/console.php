<?php

use App\Jobs\CheckAndDispatchSlatePoolBuilds;
use App\Jobs\LiveScoreRefreshJob;
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

// Refresh live scores for in-progress games (every 2 minutes)
Schedule::job(new LiveScoreRefreshJob)->everyTwoMinutes();

// Refresh odds for upcoming games with tiered cadence (every 30 minutes)
// Close games (<4h): every 30 min | Far games (>4h): 6h heartbeat | Live: skipped
Schedule::job(new OddsRefreshJob)->everyThirtyMinutes();
