<?php

namespace App\Jobs;

use App\Models\OddsSnapshot;
use App\Models\PickSelection;
use App\Services\OddsApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OddsRefreshJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $now = now();

        // Only fetch pre-game picks — live games handled by LiveScoreRefreshJob
        $picks = PickSelection::where('outcome', 'pending')
            ->where('is_drafted', true)
            ->where('game_time', '>', $now)
            ->get();

        if ($picks->isEmpty()) {
            return;
        }

        // Partition into close (<= 4h) and far (> 4h) tiers
        $fourHoursOut = $now->copy()->addHours(4);
        $closePicks = $picks->filter(fn ($p) => $p->game_time->lte($fourHoursOut));
        $farPicks = $picks->filter(fn ($p) => $p->game_time->gt($fourHoursOut));

        // Far picks only process on 6-hour heartbeat
        $heartbeatDue = !Cache::has('odds_refresh.heartbeat');
        if ($heartbeatDue && $farPicks->isNotEmpty()) {
            Cache::put('odds_refresh.heartbeat', true, now()->addHours(6));
        }

        $picksToProcess = $closePicks;
        if ($heartbeatDue) {
            $picksToProcess = $picksToProcess->merge($farPicks);
        }

        if ($picksToProcess->isEmpty()) {
            Log::info('OddsRefreshJob: no picks to process this run (far picks waiting for heartbeat)');
            return;
        }

        $oddsApi = app(OddsApiService::class);
        $totalRefreshed = 0;
        $totalSnapshots = 0;
        $capturedAt = $now;

        $grouped = $picksToProcess->groupBy('sport');

        foreach ($grouped as $sport => $sportPicks) {
            $eventIds = $sportPicks->pluck('external_id')
                ->map(fn ($id) => explode('_', $id)[0])
                ->unique()
                ->values()
                ->toArray();

            if (empty($eventIds)) {
                continue;
            }

            $result = $oddsApi->fetchCurrentOddsMap($eventIds, $sport);
            $oddsMap = $result['odds'];
            $pointsMap = $result['points'];

            foreach ($sportPicks as $pick) {
                if (!isset($oddsMap[$pick->external_id])) {
                    continue;
                }

                $newOdds = $oddsMap[$pick->external_id];

                // Skip player props if the bookmaker has shifted the line
                if ($pick->pick_type === 'player_prop') {
                    $apiPoint = $pointsMap[$pick->external_id] ?? null;
                    $originalPoint = $this->extractPointFromDescription($pick->description);

                    if ($apiPoint !== null && $originalPoint !== null && abs($apiPoint - $originalPoint) > 0.01) {
                        continue;
                    }
                }

                // Update current odds on the pick
                $pick->update([
                    'current_odds' => $newOdds,
                    'odds_updated_at' => now(),
                ]);
                $totalRefreshed++;

                // Capture odds snapshot for drift charts (replaces OddsHistoryJob)
                $lineValue = $pointsMap[$pick->external_id] ?? null;
                if ($pick->pick_type === 'moneyline') {
                    $lineValue = null;
                }

                $exists = OddsSnapshot::where('pick_selection_id', $pick->id)
                    ->where('captured_at', $capturedAt)
                    ->exists();

                if (!$exists) {
                    OddsSnapshot::create([
                        'pick_selection_id' => $pick->id,
                        'odds' => $newOdds,
                        'line' => $lineValue !== null ? (float) $lineValue : null,
                        'captured_at' => $capturedAt,
                    ]);
                    $totalSnapshots++;
                }
            }
        }

        Log::info("OddsRefreshJob: odds_refreshed={$totalRefreshed}, snapshots={$totalSnapshots}, heartbeat=" . ($heartbeatDue ? 'yes' : 'no'));
    }

    private function extractPointFromDescription(string $description): ?float
    {
        if (preg_match('/(?:Over|Under)\s+([\d.]+)/i', $description, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }
}
