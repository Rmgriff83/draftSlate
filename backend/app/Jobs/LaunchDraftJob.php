<?php

namespace App\Jobs;

use App\Events\DraftStarted;
use App\Exceptions\InsufficientPicksException;
use App\Models\DraftState;
use App\Models\League;
use App\Models\PickSelection;
use App\Models\SlatePool;
use App\Services\DraftService;
use App\Services\OddsApiService;
use App\Services\PoolCurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class LaunchDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $leagueId,
        public int $week,
    ) {
        $this->onQueue('draft-high');
    }

    public function handle(
        DraftService $draftService,
        OddsApiService $oddsApi,
        PoolCurationService $curationService,
    ): void {
        $league = League::find($this->leagueId);
        if (!$league) {
            return;
        }

        // Guard: don't re-launch if an active/completed draft already exists for this week
        $existingDraft = DraftState::where('league_id', $this->leagueId)
            ->where('week', $this->week)
            ->whereIn('status', ['active', 'completed'])
            ->first();

        if ($existingDraft) {
            Log::info("LaunchDraftJob: Draft already exists for league {$this->leagueId} week {$this->week}, skipping");
            return;
        }

        // Build the pool inline
        $slatePool = $this->buildPool($league, $oddsApi, $curationService);
        if (!$slatePool) {
            return;
        }

        // Initialize draft (generates order, sets active with countdown)
        $draftState = $draftService->initializeDraft($league, $slatePool);

        // Week 1: transition league from pending → active
        if ($league->state === 'pending') {
            $updateData = [
                'state' => 'active',
                'current_week' => $this->week ?: 1,
            ];

            if (!$league->season_start_date) {
                $updateData['season_start_date'] = now()->tz($league->draft_timezone)->toDateString();
            }

            $league->update($updateData);
        }

        // Broadcast so users enter draft room
        event(new DraftStarted($draftState));

        // Schedule picks to begin after countdown
        $countdownMinutes = config('draftslate.draft.pre_draft_countdown_minutes', 12);
        BeginFirstPickJob::dispatch(
            $draftState->id,
            $draftState->draft_starts_at->toISOString(),
        )->delay(now()->addMinutes($countdownMinutes));

        Log::info("LaunchDraftJob: Draft launched for league {$this->leagueId} week {$this->week}, picks begin at {$draftState->draft_starts_at->toISOString()}");
    }

    private function buildPool(
        League $league,
        OddsApiService $oddsApi,
        PoolCurationService $curationService,
    ): ?SlatePool {
        // Check for existing ready pool with picks
        $slatePool = SlatePool::where('league_id', $league->id)
            ->where('week', $this->week)
            ->where('status', 'ready')
            ->first();

        if ($slatePool && PickSelection::where('slate_pool_id', $slatePool->id)->count() > 0) {
            return $slatePool;
        }

        // Reuse a stale "building" pool or create new
        $slatePool = SlatePool::where('league_id', $league->id)
            ->where('week', $this->week)
            ->where('status', 'building')
            ->first();

        if ($slatePool) {
            PickSelection::where('slate_pool_id', $slatePool->id)->delete();
            $slatePool->update(['snapshot_at' => now()]);
        } else {
            $slatePool = SlatePool::create([
                'league_id' => $league->id,
                'week' => $this->week,
                'snapshot_at' => now(),
                'status' => 'building',
            ]);
        }

        // Fetch picks from all selected sports
        $allPicks = $oddsApi->fetchForSports($league->sports ?? ['basketball_nba']);

        // Filter by matchup window
        $cutoffTime = now()->addHours($league->min_hours_before_game);
        $windowEnd = now()->addDays($league->matchup_duration_days);

        $filteredPicks = array_filter($allPicks, function ($pick) use ($cutoffTime, $windowEnd) {
            if (!empty($pick['game_time'])) {
                $gameTime = Carbon::parse($pick['game_time']);
                if ($gameTime->lte($cutoffTime) || $gameTime->gt($windowEnd)) {
                    return false;
                }
            }
            return true;
        });

        // Deduplicate by external_id
        $uniquePicks = [];
        foreach ($filteredPicks as $pick) {
            $uniquePicks[$pick['external_id']] = $pick;
        }

        // Filter out pick types not in roster config
        $rosterConfig = $league->roster_config ?? [];
        $allowedTypes = array_keys(array_filter($rosterConfig, fn ($count) => $count > 0));

        if (!empty($allowedTypes)) {
            $uniquePicks = array_filter($uniquePicks, fn ($pick) =>
                in_array($pick['pick_type'] ?? '', $allowedTypes)
            );
        }

        if (empty($uniquePicks)) {
            Log::error("LaunchDraftJob: No picks available for league {$this->leagueId} week {$this->week}");
            $slatePool->update(['status' => 'failed']);
            return null;
        }

        // Curate a balanced pool
        $memberCount = $league->memberships()->count();

        try {
            $curation = $curationService->curate(array_values($uniquePicks), $league, $memberCount);
        } catch (InsufficientPicksException $e) {
            Log::error("LaunchDraftJob: Insufficient picks for league {$this->leagueId} week {$this->week}: {$e->getMessage()}");
            $slatePool->update(['status' => 'failed']);
            return null;
        }

        // Insert curated pick selections
        $insertData = [];
        foreach ($curation['picks'] as $pick) {
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

        $slatePool->update([
            'status' => 'ready',
            'api_metadata' => [
                'auto_launch' => true,
                'sports' => $league->sports,
                'total_raw_picks' => count($allPicks),
                'filtered_picks' => count($filteredPicks),
                'curated_picks' => count($insertData),
                'curation' => $curation['metadata'],
                'built_at' => now()->toISOString(),
            ],
        ]);

        return $slatePool;
    }
}
