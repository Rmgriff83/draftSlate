<?php

namespace App\Http\Controllers;

use App\Models\DraftState;
use App\Models\League;
use App\Models\PickSelection;
use App\Models\SlatePick;
use App\Services\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    public function __construct(
        private DraftService $draftService,
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

        // Show lobby when no draft exists, or when the latest draft is completed
        // and the league has advanced to the next week (ready for a new draft)
        if (!$draft || ($draft->status === 'completed' && $draft->week < $league->current_week)) {
            return response()->json([
                'data' => [
                    'league_id' => $league->id,
                    'status' => 'lobby',
                    'pick_timer_seconds' => $league->pick_timer_seconds,
                    'roster_config' => $league->roster_config,
                    'bench_slots' => $league->getBenchSlotsCount(),
                    'aggregate_odds_floor' => $league->aggregate_odds_floor,
                    'matchup_duration_days' => $league->matchup_duration_days,
                    'my_membership_id' => $membership->id,
                    'is_my_turn' => false,
                    'members' => $members->map(fn ($m) => [
                        'id' => $m->id,
                        'team_name' => $m->team_name,
                        'user_name' => $m->user->display_name,
                        'user_id' => $m->user->id,
                        'avatar_url' => $m->user->avatar_url,
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
                'bench_slots' => $league->getBenchSlotsCount(),
                'aggregate_odds_floor' => $league->aggregate_odds_floor,
                'matchup_duration_days' => $league->matchup_duration_days,
                'started_at' => $draft->started_at?->toISOString(),
                'draft_starts_at' => $draft->draft_starts_at?->toISOString(),
                'completed_at' => $draft->completed_at?->toISOString(),
                'my_membership_id' => $membership->id,
                'picks_started' => $draft->current_pick_started_at !== null,
                'is_my_turn' => $draft->current_drafter_id === $membership->id && $draft->current_pick_started_at !== null,
                'auto_draft_members' => $draft->auto_draft_members ?? [],
                'members' => $members->map(fn ($m) => [
                    'id' => $m->id,
                    'team_name' => $m->team_name,
                    'user_name' => $m->user->display_name,
                    'user_id' => $m->user->id,
                    'avatar_url' => $m->user->avatar_url,
                ]),
                'picks' => $allPicks->map(fn ($p) => [
                    'id' => $p->id,
                    'drafter_id' => $p->league_membership_id,
                    'drafter_team' => $p->membership->team_name,
                    'description' => $p->pickSelection->description,
                    'pick_type' => $p->pickSelection->pick_type,
                    'sport' => $p->pickSelection->sport,
                    'player_name' => $p->pickSelection->player_name,
                    'home_team' => $p->pickSelection->home_team,
                    'away_team' => $p->pickSelection->away_team,
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
     * POST /leagues/{league}/draft/autodraft/disable — turn off autodraft for the current user.
     */
    public function disableAutoDraft(Request $request, League $league): JsonResponse
    {
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

        if (!$draft->isInAutoDraft($membership->id)) {
            return response()->json(['message' => 'Autodraft is not enabled for you.'], 422);
        }

        $this->draftService->disableAutoDraft($draft, $membership);

        return response()->json(['message' => 'Autodraft disabled.']);
    }

}
