<?php

namespace App\Jobs;

use App\Events\SlatePoolReady;
use App\Models\DraftState;
use App\Models\JobLog;
use App\Models\League;
use App\Models\PickSelection;
use App\Models\SlatePool;
use App\Services\OddsApiService;
use App\Services\OddsMathService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SlatePoolBuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public int $leagueId,
        public int $week,
    ) {
        $this->onQueue('low');
    }

    public function handle(OddsApiService $oddsApi, OddsMathService $oddsMath): void
    {
        $jobLog = JobLog::create([
            'job_type' => 'SlatePoolBuildJob',
            'league_id' => $this->leagueId,
            'week' => $this->week,
            'status' => 'started',
            'started_at' => now(),
        ]);

        try {
            $league = League::findOrFail($this->leagueId);

            // Check for existing pool
            $existing = SlatePool::where('league_id', $this->leagueId)
                ->where('week', $this->week)
                ->first();

            if ($existing) {
                Log::info("SlatePoolBuildJob: Pool already exists for league {$this->leagueId} week {$this->week}");
                $jobLog->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'context' => ['skipped' => 'pool_already_exists'],
                ]);
                return;
            }

            // Create the slate pool
            $slatePool = SlatePool::create([
                'league_id' => $this->leagueId,
                'week' => $this->week,
                'snapshot_at' => now(),
                'status' => 'building',
            ]);

            // Fetch odds data
            $playerProps = $oddsApi->fetchNflPlayerProps();
            $gameLines = $oddsApi->fetchNflGameLines();
            $allPicks = array_merge($playerProps, $gameLines);

            // Filter picks based on league rules — matchup window + odds
            $cutoffTime = now()->addHours($league->min_hours_before_game);
            $windowEnd = now()->addDays($league->matchup_duration_days);

            $filteredPicks = array_filter($allPicks, function ($pick) use ($league, $oddsMath, $cutoffTime, $windowEnd) {
                // Exclude games outside the matchup window
                $gameTime = Carbon::parse($pick['game_time']);
                if ($gameTime->lte($cutoffTime) || $gameTime->gt($windowEnd)) {
                    return false;
                }

                // Apply odds enforcement
                if ($league->odds_mode === 'global_floor') {
                    return $oddsMath->meetsOddsFloor($pick['snapshot_odds'], $league->global_odds_floor);
                }

                // For per_slot_bands, include picks that fit any band
                if ($league->odds_mode === 'per_slot_bands' && is_array($league->slot_bands)) {
                    foreach ($league->slot_bands as $band) {
                        if ($oddsMath->isWithinBand($pick['snapshot_odds'], $band['min'], $band['max'])) {
                            return true;
                        }
                    }
                    return false;
                }

                return true;
            });

            // Deduplicate by external_id
            $uniquePicks = [];
            foreach ($filteredPicks as $pick) {
                $uniquePicks[$pick['external_id']] = $pick;
            }

            // Batch insert pick selections
            $insertData = [];
            foreach ($uniquePicks as $pick) {
                $pick['game_time'] = Carbon::parse($pick['game_time']);
                $insertData[] = array_merge($pick, [
                    'slate_pool_id' => $slatePool->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (!empty($insertData)) {
                foreach (array_chunk($insertData, 100) as $chunk) {
                    PickSelection::insert($chunk);
                }
            }

            // Update pool status and metadata
            $pickCount = count($insertData);
            $slatePool->update([
                'status' => 'ready',
                'api_metadata' => [
                    'total_raw_picks' => count($allPicks),
                    'filtered_picks' => $pickCount,
                    'props_count' => count($playerProps),
                    'lines_count' => count($gameLines),
                    'built_at' => now()->toISOString(),
                ],
            ]);

            // Create or update draft state
            DraftState::updateOrCreate(
                ['league_id' => $this->leagueId, 'week' => $this->week],
                [
                    'slate_pool_id' => $slatePool->id,
                    'status' => 'preparing',
                    'draft_order' => [],
                    'total_rounds' => $league->getTotalRounds(),
                ]
            );

            // Broadcast that the pool is ready
            event(new SlatePoolReady($slatePool));

            $jobLog->update([
                'status' => 'completed',
                'completed_at' => now(),
                'context' => [
                    'pick_count' => $pickCount,
                    'pool_id' => $slatePool->id,
                ],
            ]);

            Log::info("SlatePoolBuildJob: Built pool for league {$this->leagueId} week {$this->week} with {$pickCount} picks");
        } catch (\Exception $e) {
            $jobLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            Log::error("SlatePoolBuildJob: Failed for league {$this->leagueId} week {$this->week}", [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
