<?php

namespace App\Http\Controllers;

use App\Models\DraftState;
use App\Models\League;
use App\Models\PickSelection;
use App\Models\SlatePick;
use App\Models\SlatePool;
use App\Exceptions\InsufficientPicksException;
use App\Services\DraftService;
use App\Services\OddsApiService;
use App\Services\OddsMathService;
use App\Services\PoolCurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DraftController extends Controller
{
    public function __construct(
        private DraftService $draftService,
        private OddsApiService $oddsApi,
        private OddsMathService $oddsMath,
        private PoolCurationService $curationService,
    ) {}

    /**
     * GET /leagues/{league}/draft — get current draft state.
     */
    public function show(Request $request, League $league): JsonResponse
    {
        $membership = $league->memberships()->where('user_id', $request->user()->id)->first();
        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $members = $league->memberships()->with('user')->get();

        $draft = DraftState::where('league_id', $league->id)
            ->latest('week')
            ->first();

        // No draft yet — return lobby state so the frontend shows the waiting room
        if (!$draft) {
            return response()->json([
                'data' => [
                    'league_id' => $league->id,
                    'status' => 'lobby',
                    'pick_timer_seconds' => $league->pick_timer_seconds,
                    'roster_config' => $league->roster_config,
                    'aggregate_odds_floor' => $league->aggregate_odds_floor,
                    'my_membership_id' => $membership->id,
                    'is_my_turn' => false,
                    'members' => $members->map(fn ($m) => [
                        'id' => $m->id,
                        'team_name' => $m->team_name,
                        'user_name' => $m->user->display_name,
                        'user_id' => $m->user->id,
                    ]),
                    'picks' => [],
                ],
            ]);
        }

        // Get all picks made so far
        $allPicks = SlatePick::where('slate_pool_id', $draft->slate_pool_id)
            ->with(['pickSelection', 'membership'])
            ->orderBy('draft_pick_number')
            ->get();

        return response()->json([
            'data' => [
                'id' => $draft->id,
                'league_id' => $draft->league_id,
                'week' => $draft->week,
                'status' => $draft->status,
                'draft_order' => $draft->draft_order,
                'draft_order_weights' => $draft->draft_order_weights,
                'current_round' => $draft->current_round,
                'current_pick_index' => $draft->current_pick_index,
                'current_drafter_id' => $draft->current_drafter_id,
                'current_pick_started_at' => $draft->current_pick_started_at?->toISOString(),
                'total_rounds' => $draft->total_rounds,
                'pick_timer_seconds' => $league->pick_timer_seconds,
                'roster_config' => $league->roster_config,
                'aggregate_odds_floor' => $league->aggregate_odds_floor,
                'started_at' => $draft->started_at?->toISOString(),
                'completed_at' => $draft->completed_at?->toISOString(),
                'my_membership_id' => $membership->id,
                'is_my_turn' => $draft->current_drafter_id === $membership->id,
                'members' => $members->map(fn ($m) => [
                    'id' => $m->id,
                    'team_name' => $m->team_name,
                    'user_name' => $m->user->display_name,
                    'user_id' => $m->user->id,
                ]),
                'picks' => $allPicks->map(fn ($p) => [
                    'id' => $p->id,
                    'drafter_id' => $p->league_membership_id,
                    'drafter_team' => $p->membership->team_name,
                    'description' => $p->pickSelection->description,
                    'pick_type' => $p->pickSelection->pick_type,
                    'sport' => $p->pickSelection->sport,
                    'game_display' => $p->pickSelection->game_display,
                    'game_time' => $p->pickSelection->game_time?->toISOString(),
                    'snapshot_odds' => $p->pickSelection->snapshot_odds,
                    'drafted_odds' => $p->drafted_odds,
                    'position' => $p->position,
                    'slot_number' => $p->slot_number,
                    'slot_type' => $p->slot_type,
                    'round' => $p->draft_round,
                    'pick_number' => $p->draft_pick_number,
                ]),
            ],
        ]);
    }

    /**
     * GET /leagues/{league}/draft/pool — get available picks.
     */
    public function getPool(Request $request, League $league): JsonResponse
    {
        $membership = $league->memberships()->where('user_id', $request->user()->id)->first();
        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $draft = DraftState::where('league_id', $league->id)
            ->latest('week')
            ->first();

        if (!$draft) {
            return response()->json(['message' => 'No draft found.'], 404);
        }

        $picks = PickSelection::where('slate_pool_id', $draft->slate_pool_id)
            ->where('is_drafted', false)
            ->orderBy('snapshot_odds', 'desc')
            ->get();

        return response()->json([
            'data' => $picks->map(fn ($p) => [
                'id' => $p->id,
                'description' => $p->description,
                'pick_type' => $p->pick_type,
                'category' => $p->category,
                'player_name' => $p->player_name,
                'home_team' => $p->home_team,
                'away_team' => $p->away_team,
                'game_display' => $p->game_display,
                'game_time' => $p->game_time?->toISOString(),
                'sport' => $p->sport,
                'snapshot_odds' => $p->snapshot_odds,
                'current_odds' => $p->current_odds,
            ]),
        ]);
    }

    /**
     * POST /leagues/{league}/draft/pick — submit a draft pick.
     */
    public function submitPick(Request $request, League $league): JsonResponse
    {
        $validated = $request->validate([
            'pick_selection_id' => ['required', 'integer'],
            'slot_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $membership = $league->memberships()->where('user_id', $request->user()->id)->first();
        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $draft = DraftState::where('league_id', $league->id)
            ->where('status', 'active')
            ->latest('week')
            ->first();

        if (!$draft) {
            return response()->json(['message' => 'No active draft.'], 404);
        }

        $pick = PickSelection::find($validated['pick_selection_id']);
        if (!$pick) {
            return response()->json(['message' => 'Pick selection not found.'], 404);
        }

        try {
            $slatePick = $this->draftService->submitPick(
                $draft,
                $membership,
                $pick,
                $validated['slot_number'] ?? null,
            );

            return response()->json([
                'data' => [
                    'id' => $slatePick->id,
                    'description' => $pick->description,
                    'drafted_odds' => $slatePick->drafted_odds,
                    'position' => $slatePick->position,
                    'slot_number' => $slatePick->slot_number,
                    'slot_type' => $slatePick->slot_type,
                    'round' => $slatePick->draft_round,
                    'pick_number' => $slatePick->draft_pick_number,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /leagues/{league}/draft/order — get draft order with weights.
     */
    public function getOrder(Request $request, League $league): JsonResponse
    {
        $membership = $league->memberships()->where('user_id', $request->user()->id)->first();
        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $draft = DraftState::where('league_id', $league->id)
            ->latest('week')
            ->first();

        if (!$draft) {
            return response()->json(['message' => 'No draft found.'], 404);
        }

        $members = $league->memberships()->with('user')->get()->keyBy('id');
        $order = [];

        foreach ($draft->draft_order as $position => $membershipId) {
            $member = $members->get($membershipId);
            $weight = collect($draft->draft_order_weights)
                ->firstWhere('membership_id', $membershipId);

            $order[] = [
                'position' => $position + 1,
                'membership_id' => $membershipId,
                'team_name' => $member?->team_name,
                'user_name' => $member?->user?->display_name,
                'prior_correct' => $weight['prior_correct'] ?? 0,
                'weight' => $weight['weight'] ?? 1,
            ];
        }

        return response()->json(['data' => $order]);
    }

    /**
     * POST /leagues/{league}/draft/start — commissioner starts the draft.
     */
    public function start(Request $request, League $league): JsonResponse
    {
        if ($league->commissioner_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the commissioner can start the draft.'], 403);
        }

        if ($league->state !== 'pending') {
            // Allow starting draft from pending state
        }

        $slatePool = SlatePool::where('league_id', $league->id)
            ->where('status', 'ready')
            ->latest('week')
            ->first();

        // Check if existing pool actually has picks
        if ($slatePool && PickSelection::where('slate_pool_id', $slatePool->id)->count() === 0) {
            $slatePool = null; // Treat empty pool as non-existent
        }

        if (!$slatePool) {
            // Reuse a stale "building" pool from a prior failed attempt, or create new
            $slatePool = SlatePool::where('league_id', $league->id)
                ->where('status', 'building')
                ->latest('week')
                ->first();

            if ($slatePool) {
                // Clean out any partial picks from the failed attempt
                PickSelection::where('slate_pool_id', $slatePool->id)->delete();
                $slatePool->update(['snapshot_at' => now()]);
            } else {
                $slatePool = SlatePool::create([
                    'league_id' => $league->id,
                    'week' => 1,
                    'snapshot_at' => now(),
                    'status' => 'building',
                ]);
            }

            // Fetch picks from all selected sports
            $allPicks = $this->oddsApi->fetchForSports($league->sports ?? ['basketball_nba']);

            // Filter by matchup window — only include games that start after the
            // min-hours cutoff AND before the matchup duration window ends.
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

            if (empty($uniquePicks)) {
                return response()->json([
                    'message' => 'No picks available from the Odds API. This likely means the selected sports are in their offseason or no upcoming games were found.',
                ], 422);
            }

            // Curate a scarce, balanced pool
            $memberCount = $league->memberships()->count();

            try {
                $curation = $this->curationService->curate(array_values($uniquePicks), $league, $memberCount);
            } catch (InsufficientPicksException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'context' => $e->context,
                ], 422);
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
                    'manual_start' => true,
                    'sports' => $league->sports,
                    'total_raw_picks' => count($allPicks),
                    'filtered_picks' => count($filteredPicks),
                    'curated_picks' => count($insertData),
                    'curation' => $curation['metadata'],
                    'built_at' => now()->toISOString(),
                ],
            ]);
        }

        // Transition league to active, auto-set season_start_date if not set
        $updateData = [
            'state' => 'active',
            'current_week' => $slatePool->week ?: 1,
        ];

        if (!$league->season_start_date) {
            $updateData['season_start_date'] = now()->tz($league->draft_timezone)->toDateString();
        }

        $league->update($updateData);

        $draft = $this->draftService->initializeDraft($league, $slatePool);

        return response()->json([
            'data' => [
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'message' => 'Draft started!',
            ],
        ]);
    }
}
