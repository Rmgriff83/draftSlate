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
use Illuminate\Support\Facades\Log;

class OddsHistoryJob implements ShouldQueue
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
        // Get all drafted pending picks whose games haven't started yet
        $picks = PickSelection::where('outcome', 'pending')
            ->where('is_drafted', true)
            ->where('game_time', '>', now())
            ->get();

        if ($picks->isEmpty()) {
            return;
        }

        $oddsApi = app(OddsApiService::class);
        $grouped = $picks->groupBy('sport');
        $totalSnapshots = 0;

        foreach ($grouped as $sport => $sportPicks) {
            $response = $oddsApi->fetchHistoricalOdds($sport, now()->toIso8601String());

            if ($response === null || empty($response['data'])) {
                continue;
            }

            $capturedAt = $response['timestamp'] ?? now()->toIso8601String();

            // Index events by ID for fast lookup
            $eventsById = [];
            foreach ($response['data'] as $event) {
                $eventId = $event['id'] ?? '';
                if ($eventId) {
                    $eventsById[$eventId] = $event;
                }
            }

            foreach ($sportPicks as $pick) {
                $eventId = explode('_', $pick->external_id)[0];

                if (!isset($eventsById[$eventId])) {
                    continue;
                }

                $event = $eventsById[$eventId];
                $oddsValue = $this->extractOddsForPick($pick, $event);

                if ($oddsValue === null) {
                    continue;
                }

                $lineValue = $this->extractLineForPick($pick, $event);

                // Dedup: skip if we already have a snapshot at this captured_at
                $exists = OddsSnapshot::where('pick_selection_id', $pick->id)
                    ->where('captured_at', $capturedAt)
                    ->exists();

                if ($exists) {
                    continue;
                }

                OddsSnapshot::create([
                    'pick_selection_id' => $pick->id,
                    'odds' => $oddsValue,
                    'line' => $lineValue,
                    'captured_at' => $capturedAt,
                ]);

                $totalSnapshots++;
            }
        }

        Log::info("OddsHistoryJob: captured {$totalSnapshots} new snapshots");
    }

    /**
     * Extract the American odds price for this pick from the historical event data.
     */
    private function extractOddsForPick(PickSelection $pick, array $event): ?int
    {
        foreach ($event['bookmakers'] ?? [] as $bookmaker) {
            foreach ($bookmaker['markets'] ?? [] as $market) {
                $marketKey = $market['key'] ?? '';

                foreach ($market['outcomes'] ?? [] as $outcome) {
                    $odds = $outcome['price'] ?? null;
                    if ($odds === null) {
                        continue;
                    }

                    $name = $outcome['name'] ?? '';
                    $playerName = $outcome['description'] ?? null;

                    // Build the external_id the same way we build it during pool creation
                    if ($playerName) {
                        $candidateId = "{$event['id']}_{$marketKey}_{$playerName}_{$name}";
                    } else {
                        $candidateId = "{$event['id']}_{$marketKey}_{$name}";
                    }

                    if ($candidateId === $pick->external_id) {
                        return (int) $odds;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract the point/line value for spread/total/prop picks.
     */
    private function extractLineForPick(PickSelection $pick, array $event): ?float
    {
        if ($pick->pick_type === 'moneyline') {
            return null;
        }

        foreach ($event['bookmakers'] ?? [] as $bookmaker) {
            foreach ($bookmaker['markets'] ?? [] as $market) {
                $marketKey = $market['key'] ?? '';

                foreach ($market['outcomes'] ?? [] as $outcome) {
                    $name = $outcome['name'] ?? '';
                    $playerName = $outcome['description'] ?? null;
                    $point = $outcome['point'] ?? null;

                    if ($playerName) {
                        $candidateId = "{$event['id']}_{$marketKey}_{$playerName}_{$name}";
                    } else {
                        $candidateId = "{$event['id']}_{$marketKey}_{$name}";
                    }

                    if ($candidateId === $pick->external_id) {
                        return $point !== null ? (float) $point : null;
                    }
                }
            }
        }

        return null;
    }
}
