<?php

namespace App\Jobs;

use App\Models\League;
use App\Models\Matchup;
use App\Models\Season;
use App\Models\SlatePool;
use App\Services\MatchupSchedulerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMatchupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public int $leagueId,
        public int $week,
    ) {
        $this->onQueue('default');
    }

    public function handle(MatchupSchedulerService $scheduler): void
    {
        $league = League::find($this->leagueId);

        if (!$league) {
            Log::warning("GenerateMatchupsJob: League {$this->leagueId} not found");
            return;
        }

        // Create Season record if this is week 1
        if ($this->week === 1) {
            Season::firstOrCreate(
                ['league_id' => $league->id, 'year' => now()->year],
                [
                    'start_week' => 1,
                    'end_week' => $league->regular_season_weeks ?? 13,
                    'playoff_start_week' => ($league->regular_season_weeks ?? 13) + 1,
                    'status' => 'active',
                ],
            );

            // Generate the full season schedule
            $scheduler->generateSeasonSchedule($league);

            Log::info("GenerateMatchupsJob: Generated full season schedule for league {$league->id}");
        } else {
            // Set existing matchups for this week to in_progress
            Matchup::where('league_id', $league->id)
                ->where('week', $this->week)
                ->where('status', 'scheduled')
                ->update(['status' => 'in_progress']);

            Log::info("GenerateMatchupsJob: Activated week {$this->week} matchups for league {$league->id}");
        }

        // Update league state and current week
        $league->update([
            'state' => 'active',
            'current_week' => $this->week,
        ]);

        // Update SlatePool status to active
        SlatePool::where('league_id', $league->id)
            ->where('week', $this->week)
            ->update(['status' => 'active']);
    }
}
