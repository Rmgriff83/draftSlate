<?php

namespace App\Services;

use App\Events\DraftAdvanced;
use App\Events\DraftCompleted;
use App\Events\DraftPickMade;
use App\Events\DraftStarted;
use App\Jobs\DraftAutoPickJob;
use App\Models\DraftState;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\PickSelection;
use App\Models\SlatePick;
use App\Models\SlatePool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DraftService
{
    public function __construct(
        private DraftOrderService $orderService,
        private OddsMathService $oddsMath,
    ) {}

    /**
     * Initialize the draft: create DraftState, generate order, broadcast.
     */
    public function initializeDraft(League $league, SlatePool $slatePool): DraftState
    {
        $week = $slatePool->week;
        $totalRounds = $league->getTotalRounds();
        $orderData = $this->orderService->generateDraftOrder($league, $week);

        $draftState = DraftState::updateOrCreate(
            ['league_id' => $league->id, 'week' => $week],
            [
                'slate_pool_id' => $slatePool->id,
                'status' => 'active',
                'draft_order' => $orderData['order'],
                'draft_order_weights' => $orderData['weights'],
                'current_round' => 1,
                'current_pick_index' => 0,
                'current_drafter_id' => $orderData['order'][0] ?? null,
                'current_pick_started_at' => now(),
                'total_rounds' => $totalRounds,
                'started_at' => now(),
            ]
        );

        event(new DraftStarted($draftState));

        // Schedule auto-pick for the first drafter
        $this->scheduleAutoPick($draftState, $league);

        return $draftState;
    }

    /**
     * Submit a manual pick. Validates eligibility, odds, slot assignment.
     */
    public function submitPick(
        DraftState $draft,
        LeagueMembership $drafter,
        PickSelection $pick,
        ?int $slotNumber = null,
    ): SlatePick {
        return DB::transaction(function () use ($draft, $drafter, $pick, $slotNumber) {
            // Lock the draft state row
            $draft = DraftState::lockForUpdate()->find($draft->id);

            // Validate it's this drafter's turn
            if ($draft->current_drafter_id !== $drafter->id) {
                throw new \RuntimeException('It is not your turn to pick.');
            }

            if ($draft->status !== 'active') {
                throw new \RuntimeException('The draft is not active.');
            }

            // Validate pick is available
            $pick = PickSelection::lockForUpdate()->find($pick->id);
            if ($pick->is_drafted) {
                throw new \RuntimeException('This pick has already been drafted.');
            }

            $league = $draft->league;

            // Get existing picks for this drafter
            $existingPicks = SlatePick::where('league_membership_id', $drafter->id)
                ->where('slate_pool_id', $draft->slate_pool_id)
                ->get();

            // Determine slot assignment based on pick type + roster_config
            $rosterConfig = $league->roster_config;
            $pickType = $pick->pick_type;

            $typeCount = $existingPicks->where('position', 'starter')
                ->where('slot_type', $pickType)->count();
            $typeMax = $rosterConfig[$pickType] ?? 0;

            $benchCount = $existingPicks->where('position', 'bench')->count();

            if ($typeCount < $typeMax) {
                $position = 'starter';
                $slot = $slotNumber ?? ($typeCount + 1);
                $slotType = $pickType;
            } else {
                // All starter slots for this type are filled — goes to bench
                $totalBench = $league->getBenchSlotsCount();
                if ($benchCount >= $totalBench) {
                    throw new \RuntimeException('All slots are filled.');
                }
                $position = 'bench';
                $slot = $slotNumber ?? ($benchCount + 1);
                $slotType = $pickType;
            }

            // Validate aggregate odds for starter picks
            if ($position === 'starter') {
                $starterOdds = $existingPicks->where('position', 'starter')
                    ->pluck('drafted_odds')->toArray();
                $newOdds = $pick->current_odds ?? $pick->snapshot_odds;
                if (!$this->oddsMath->aggregateMeetsFloor($starterOdds, $newOdds, $league->aggregate_odds_floor)) {
                    throw new \RuntimeException('This pick would exceed the aggregate odds limit.');
                }
            }

            // Calculate snake sequence to get overall pick number
            $snakeSequence = $this->orderService->buildSnakeSequence(
                $draft->draft_order,
                $draft->total_rounds
            );
            $overallPickNumber = $draft->current_pick_index + 1;

            // Create the slate pick
            $slatePick = SlatePick::create([
                'league_membership_id' => $drafter->id,
                'pick_selection_id' => $pick->id,
                'slate_pool_id' => $draft->slate_pool_id,
                'week' => $draft->week,
                'position' => $position,
                'slot_number' => $slot,
                'slot_type' => $slotType,
                'drafted_odds' => $pick->current_odds ?? $pick->snapshot_odds,
                'draft_round' => $draft->current_round,
                'draft_pick_number' => $overallPickNumber,
            ]);

            // Mark pick as drafted
            $pick->update(['is_drafted' => true]);

            // Broadcast pick made
            event(new DraftPickMade($draft, $slatePick, $drafter, false));

            // Advance the draft
            $this->advanceDraft($draft);

            return $slatePick;
        });
    }

    /**
     * Auto-pick: select best available pick for the current drafter.
     * All logic runs inside the transaction to match submitPick's pattern.
     *
     * Priority order:
     *  1. Fill unfilled starter slot with type-matching + aggregate-safe pick
     *  2. Fill unfilled starter slot with type-matching pick (skip aggregate — auto-pick is lenient)
     *  3. Bench (only when ALL starter types are filled)
     */
    public function autoPickForDrafter(DraftState $draft, LeagueMembership $drafter): SlatePick
    {
        return DB::transaction(function () use ($draft, $drafter) {
            $draft = DraftState::lockForUpdate()->find($draft->id);

            if ($draft->current_drafter_id !== $drafter->id) {
                throw new \RuntimeException('Drafter mismatch during auto-pick.');
            }

            $league = League::find($draft->league_id);
            $rosterConfig = $league->roster_config ?? [];

            $existingPicks = SlatePick::where('league_membership_id', $drafter->id)
                ->where('slate_pool_id', $draft->slate_pool_id)
                ->get();

            $unfilledTypes = $league->getUnfilledStarterSlots($existingPicks);

            $availablePicks = PickSelection::where('slate_pool_id', $draft->slate_pool_id)
                ->where('is_drafted', false)
                ->orderByRaw('ABS(snapshot_odds) ASC')
                ->orderBy('game_time', 'asc')
                ->get();

            if ($availablePicks->isEmpty()) {
                throw new \RuntimeException('No available picks remaining.');
            }

            Log::info('DraftService: Auto-pick starting', [
                'drafter_id' => $drafter->id,
                'roster_config' => $rosterConfig,
                'unfilled_types' => $unfilledTypes,
                'existing_starters' => $existingPicks->where('position', 'starter')->count(),
                'existing_bench' => $existingPicks->where('position', 'bench')->count(),
                'available_count' => $availablePicks->count(),
            ]);

            $selectedPick = null;
            $position = null;
            $slotType = null;
            $slot = null;

            // Priority 1: fill unfilled starter slot, type-matching + aggregate-safe
            if (!empty($unfilledTypes)) {
                $starterOdds = $existingPicks->where('position', 'starter')
                    ->pluck('drafted_odds')->toArray();

                foreach ($availablePicks as $candidate) {
                    if (!isset($unfilledTypes[$candidate->pick_type])) {
                        continue;
                    }

                    $pick = PickSelection::lockForUpdate()->find($candidate->id);
                    if (!$pick || $pick->is_drafted) {
                        continue;
                    }

                    if ($this->oddsMath->aggregateMeetsFloor($starterOdds, $pick->snapshot_odds, $league->aggregate_odds_floor)) {
                        $typeCount = $existingPicks->where('position', 'starter')
                            ->where('slot_type', $pick->pick_type)->count();

                        $selectedPick = $pick;
                        $position = 'starter';
                        $slotType = $pick->pick_type;
                        $slot = $typeCount + 1;

                        Log::info('DraftService: Auto-pick P1 hit (starter + aggregate safe)', [
                            'pick_id' => $pick->id,
                            'pick_type' => $pick->pick_type,
                            'odds' => $pick->snapshot_odds,
                        ]);
                        break;
                    }
                }
            }

            // Priority 2: fill unfilled starter slot, type-matching (skip aggregate for auto-pick)
            if (!$selectedPick && !empty($unfilledTypes)) {
                foreach ($availablePicks as $candidate) {
                    if (!isset($unfilledTypes[$candidate->pick_type])) {
                        continue;
                    }

                    $pick = PickSelection::lockForUpdate()->find($candidate->id);
                    if (!$pick || $pick->is_drafted) {
                        continue;
                    }

                    $typeCount = $existingPicks->where('position', 'starter')
                        ->where('slot_type', $pick->pick_type)->count();

                    $selectedPick = $pick;
                    $position = 'starter';
                    $slotType = $pick->pick_type;
                    $slot = $typeCount + 1;

                    Log::info('DraftService: Auto-pick P2 hit (starter, aggregate skipped)', [
                        'pick_id' => $pick->id,
                        'pick_type' => $pick->pick_type,
                        'odds' => $pick->snapshot_odds,
                    ]);
                    break;
                }
            }

            // Priority 3: all starters filled — go to bench
            if (!$selectedPick) {
                foreach ($availablePicks as $candidate) {
                    $pick = PickSelection::lockForUpdate()->find($candidate->id);
                    if (!$pick || $pick->is_drafted) {
                        continue;
                    }

                    $benchCount = $existingPicks->where('position', 'bench')->count();
                    $totalBench = $league->getBenchSlotsCount();

                    if ($benchCount >= $totalBench) {
                        continue;
                    }

                    $selectedPick = $pick;
                    $position = 'bench';
                    $slotType = $pick->pick_type;
                    $slot = $benchCount + 1;

                    Log::info('DraftService: Auto-pick P3 hit (bench)', [
                        'pick_id' => $pick->id,
                        'pick_type' => $pick->pick_type,
                    ]);
                    break;
                }
            }

            if (!$selectedPick) {
                throw new \RuntimeException('No available picks remaining.');
            }

            $overallPickNumber = $draft->current_pick_index + 1;

            $slatePick = SlatePick::create([
                'league_membership_id' => $drafter->id,
                'pick_selection_id' => $selectedPick->id,
                'slate_pool_id' => $draft->slate_pool_id,
                'week' => $draft->week,
                'position' => $position,
                'slot_number' => $slot,
                'slot_type' => $slotType,
                'drafted_odds' => $selectedPick->snapshot_odds,
                'draft_round' => $draft->current_round,
                'draft_pick_number' => $overallPickNumber,
            ]);

            $selectedPick->update(['is_drafted' => true]);

            Log::info('DraftService: Auto-pick completed', [
                'drafter_id' => $drafter->id,
                'pick_id' => $selectedPick->id,
                'pick_type' => $selectedPick->pick_type,
                'position' => $position,
                'slot_type' => $slotType,
                'slot' => $slot,
                'drafted_odds' => $selectedPick->snapshot_odds,
            ]);

            event(new DraftPickMade($draft, $slatePick, $drafter, true));

            $this->advanceDraft($draft);

            return $slatePick;
        });
    }

    /**
     * Advance the draft to the next pick in the snake sequence.
     */
    public function advanceDraft(DraftState $draft): void
    {
        $snakeSequence = $this->orderService->buildSnakeSequence(
            $draft->draft_order,
            $draft->total_rounds
        );

        $nextIndex = $draft->current_pick_index + 1;

        if ($nextIndex >= count($snakeSequence)) {
            // Draft is complete
            $draft->update([
                'status' => 'completed',
                'completed_at' => now(),
                'current_pick_index' => $nextIndex,
            ]);

            // Update slate pool status
            $draft->slatePool->update(['status' => 'draft_complete']);

            event(new DraftCompleted($draft));
            return;
        }

        $nextDrafterId = $snakeSequence[$nextIndex];
        $numTeams = count($draft->draft_order);
        $nextRound = (int) floor($nextIndex / $numTeams) + 1;

        $draft->update([
            'current_pick_index' => $nextIndex,
            'current_round' => $nextRound,
            'current_drafter_id' => $nextDrafterId,
            'current_pick_started_at' => now(),
        ]);

        event(new DraftAdvanced($draft));

        // Schedule auto-pick for next drafter
        $this->scheduleAutoPick($draft, $draft->league);
    }

    /**
     * Check if draft is complete.
     */
    public function isDraftComplete(DraftState $draft): bool
    {
        $totalPicks = count($draft->draft_order) * $draft->total_rounds;
        return $draft->current_pick_index >= $totalPicks;
    }

    /**
     * Schedule an auto-pick job for the current drafter with the pick timer delay.
     */
    private function scheduleAutoPick(DraftState $draft, League $league): void
    {
        $delay = ($league->pick_timer_seconds ?? 60) + config('draftslate.draft.auto_pick_timeout_buffer', 5);

        DraftAutoPickJob::dispatch(
            $draft->id,
            $draft->current_drafter_id,
            $draft->current_pick_index,
        )->delay(now()->addSeconds($delay));

        Log::info("DraftService: Scheduled auto-pick for drafter {$draft->current_drafter_id} at index {$draft->current_pick_index} in {$delay}s");
    }
}
