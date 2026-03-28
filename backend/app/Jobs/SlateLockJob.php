<?php

namespace App\Jobs;

use App\Models\PickSelection;
use App\Models\SlatePick;
use App\Services\OddsApiService;
use App\Services\OddsMathService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SlateLockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(OddsMathService $oddsMath, OddsApiService $oddsApi): void
    {
        // Find pick selections where game time has passed but picks aren't locked
        $selectionsToLock = PickSelection::where('game_time', '<=', now())
            ->where('is_drafted', true)
            ->whereHas('slatePicks', function ($query) {
                $query->where('is_locked', false);
            })
            ->get();

        if ($selectionsToLock->isEmpty()) {
            return;
        }

        // Fetch fresh odds from API before locking
        $freshOddsMap = $this->fetchFreshOdds($selectionsToLock, $oddsApi);

        $lockCount = 0;

        foreach ($selectionsToLock as $selection) {
            // Apply fresh API odds to current_odds if available
            if (isset($freshOddsMap[$selection->external_id])) {
                $selection->update([
                    'current_odds' => $freshOddsMap[$selection->external_id],
                    'odds_updated_at' => now(),
                ]);
            }

            $unlockedPicks = $selection->slatePicks()
                ->where('is_locked', false)
                ->get();

            foreach ($unlockedPicks as $pick) {
                // Fallback chain: fresh API odds → existing current_odds → snapshot_odds
                $lockedOdds = $selection->current_odds ?? $selection->snapshot_odds;
                $drift = $oddsMath->calculateOddsDrift($pick->drafted_odds, $lockedOdds);

                $pick->update([
                    'is_locked' => true,
                    'locked_at' => now(),
                    'locked_odds' => $lockedOdds,
                    'odds_drift' => $drift,
                ]);

                $lockCount++;
            }
        }

        if ($lockCount > 0) {
            Log::info("SlateLockJob: Locked {$lockCount} picks at game kickoff (fresh odds fetched for " . count($freshOddsMap) . " selections)");
        }
    }

    /**
     * Fetch fresh odds from the API for about-to-lock selections, grouped by sport.
     *
     * @return array<string, int>  external_id => fresh American odds
     */
    private function fetchFreshOdds($selections, OddsApiService $oddsApi): array
    {
        $freshOddsMap = [];
        $grouped = $selections->groupBy('sport');

        foreach ($grouped as $sport => $sportSelections) {
            $eventIds = $sportSelections->pluck('external_id')
                ->map(fn ($id) => explode('_', $id)[0])
                ->unique()
                ->values()
                ->toArray();

            if (empty($eventIds)) {
                continue;
            }

            try {
                $result = $oddsApi->fetchCurrentOddsMap($eventIds, $sport);
                $oddsMap = $result['odds'];

                foreach ($sportSelections as $selection) {
                    if (isset($oddsMap[$selection->external_id])) {
                        $freshOddsMap[$selection->external_id] = $oddsMap[$selection->external_id];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("SlateLockJob: Failed to fetch fresh odds for {$sport}", [
                    'error' => $e->getMessage(),
                ]);
                // Continue — fallback to existing current_odds/snapshot_odds
            }
        }

        return $freshOddsMap;
    }
}
