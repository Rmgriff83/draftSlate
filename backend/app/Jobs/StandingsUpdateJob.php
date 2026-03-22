<?php

namespace App\Jobs;

use App\Events\StandingsUpdated;
use App\Models\League;
use App\Services\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StandingsUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public int $leagueId,
    ) {
        $this->onQueue('default');
    }

    public function handle(ScoringService $scoring): void
    {
        $league = League::find($this->leagueId);

        if (!$league) {
            return;
        }

        // Update standings for the current week
        $scoring->updateStandings($league, $league->current_week);

        // Calculate and apply rankings
        $rankings = $scoring->calculateRankings($league);

        $standingsData = [];

        foreach ($rankings as $rank => $membership) {
            $membership->update(['playoff_seed' => $rank + 1]);

            $standingsData[] = [
                'membership_id' => $membership->id,
                'team_name' => $membership->team_name,
                'wins' => $membership->wins,
                'losses' => $membership->losses,
                'ties' => $membership->ties,
                'total_correct_picks' => $membership->total_correct_picks,
                'playoff_seed' => $rank + 1,
            ];
        }

        event(new StandingsUpdated($this->leagueId, $league->current_week, $standingsData));

        Log::info("StandingsUpdateJob: Updated standings for league {$this->leagueId}");
    }
}
