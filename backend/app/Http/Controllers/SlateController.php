<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\Matchup;
use App\Models\SlatePick;
use App\Models\SlatePool;
use App\Services\OddsMathService;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlateController extends Controller
{
    /**
     * Get user's full slate for a week.
     */
    public function show(Request $request, League $league, int $week): JsonResponse
    {
        $user = $request->user();
        $membership = $league->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $slatePool = SlatePool::where('league_id', $league->id)
            ->where('week', $week)
            ->first();

        if (!$slatePool) {
            return response()->json(['message' => 'No slate pool exists for this week.'], 404);
        }

        $picks = SlatePick::where('league_membership_id', $membership->id)
            ->where('slate_pool_id', $slatePool->id)
            ->with('pickSelection')
            ->orderBy('position')
            ->orderBy('slot_number')
            ->get();

        return response()->json([
            'data' => [
                'week' => $week,
                'pool_status' => $slatePool->status,
                'picks' => $picks->map(fn ($pick) => $this->formatPick($pick)),
            ],
        ]);
    }

    /**
     * Combined endpoint: picks + matchup + standings in one call.
     */
    public function summary(Request $request, League $league, int $week, ScoringService $scoring): JsonResponse
    {
        $user = $request->user();
        $membership = $league->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        // --- Picks ---
        $slatePool = SlatePool::where('league_id', $league->id)
            ->where('week', $week)
            ->first();

        $picks = [];
        $poolStatus = null;

        if ($slatePool) {
            $poolStatus = $slatePool->status;
            $picks = SlatePick::where('league_membership_id', $membership->id)
                ->where('slate_pool_id', $slatePool->id)
                ->with('pickSelection')
                ->orderBy('position')
                ->orderBy('slot_number')
                ->get()
                ->map(fn ($pick) => $this->formatPick($pick))
                ->toArray();
        }

        // --- Matchup ---
        $matchupData = null;

        $matchup = Matchup::where('league_id', $league->id)
            ->where('week', $week)
            ->where(function ($q) use ($membership) {
                $q->where('home_team_id', $membership->id)
                    ->orWhere('away_team_id', $membership->id);
            })
            ->with(['homeTeam.user', 'awayTeam.user'])
            ->first();

        if ($matchup) {
            $isHome = $matchup->home_team_id === $membership->id;
            $opponentId = $isHome ? $matchup->away_team_id : $matchup->home_team_id;

            $matchupData = [
                'id' => $matchup->id,
                'week' => $matchup->week,
                'status' => $matchup->status,
                'is_playoff' => (bool) $matchup->is_playoff,
                'playoff_round' => $matchup->playoff_round,
                'is_home' => $isHome,
                'home_team' => [
                    'id' => $matchup->homeTeam->id,
                    'team_name' => $matchup->homeTeam->team_name,
                    'user_name' => $matchup->homeTeam->user->display_name ?? null,
                    'avatar_url' => $matchup->homeTeam->user->avatar_url ?? null,
                ],
                'away_team' => [
                    'id' => $matchup->awayTeam->id,
                    'team_name' => $matchup->awayTeam->team_name,
                    'user_name' => $matchup->awayTeam->user->display_name ?? null,
                    'avatar_url' => $matchup->awayTeam->user->avatar_url ?? null,
                ],
                'home_score' => $matchup->home_score,
                'away_score' => $matchup->away_score,
                'winner_id' => $matchup->winner_id,
                'is_tie' => $matchup->is_tie,
                'my_picks' => $this->getTeamPicks($membership->id, $week),
                'opponent_picks' => $this->getTeamPicks($opponentId, $week),
            ];
        }

        // --- Standings ---
        $rankings = $scoring->calculateRankings($league);

        $standings = $rankings->map(function ($member, $index) use ($membership) {
            $totalGames = $member->wins + $member->losses + $member->ties;

            return [
                'rank' => $index + 1,
                'membership_id' => $member->id,
                'user_id' => $member->user_id,
                'team_name' => $member->team_name,
                'user_name' => $member->user->display_name ?? null,
                'wins' => $member->wins,
                'losses' => $member->losses,
                'ties' => $member->ties,
                'total_correct_picks' => $member->total_correct_picks,
                'playoff_seed' => $member->playoff_seed,
                'final_position' => $member->final_position,
                'is_current_user' => $member->id === $membership->id,
                'win_percentage' => $totalGames > 0
                    ? round(($member->wins + ($member->ties * 0.5)) / $totalGames, 3)
                    : 0,
            ];
        })->values();

        return response()->json([
            'data' => [
                'week' => $week,
                'pool_status' => $poolStatus,
                'picks' => $picks,
                'matchup' => $matchupData,
                'standings' => $standings,
            ],
        ]);
    }

    private function formatPick(SlatePick $pick): array
    {
        return [
            'id' => $pick->id,
            'position' => $pick->position,
            'slot_number' => $pick->slot_number,
            'slot_type' => $pick->slot_type,
            'drafted_odds' => $pick->drafted_odds,
            'locked_odds' => $pick->locked_odds,
            'odds_drift' => $pick->odds_drift,
            'is_locked' => $pick->is_locked,
            'locked_at' => $pick->locked_at,
            'draft_round' => $pick->draft_round,
            'draft_pick_number' => $pick->draft_pick_number,
            'pick_selection' => [
                'id' => $pick->pickSelection->id,
                'description' => $pick->pickSelection->description,
                'pick_type' => $pick->pickSelection->pick_type,
                'category' => $pick->pickSelection->category,
                'player_name' => $pick->pickSelection->player_name,
                'home_team' => $pick->pickSelection->home_team,
                'away_team' => $pick->pickSelection->away_team,
                'game_display' => $pick->pickSelection->game_display,
                'game_time' => $pick->pickSelection->game_time,
                'sport' => $pick->pickSelection->sport,
                'snapshot_odds' => $pick->pickSelection->snapshot_odds,
                'current_odds' => $pick->pickSelection->current_odds,
                'outcome' => $pick->pickSelection->outcome,
                'result_data' => $pick->pickSelection->result_data,
            ],
        ];
    }

    private function getTeamPicks(int $membershipId, int $week): array
    {
        return SlatePick::where('league_membership_id', $membershipId)
            ->where('week', $week)
            ->with('pickSelection')
            ->orderBy('position')
            ->orderBy('slot_number')
            ->get()
            ->map(fn ($pick) => $this->formatPick($pick))
            ->toArray();
    }

    /**
     * Swap a pick between starter and bench.
     */
    public function swap(Request $request, League $league): JsonResponse
    {
        $validated = $request->validate([
            'pick_id' => ['required', 'integer'],
            'target_position' => ['required', 'in:starter,bench'],
            'target_slot' => ['required', 'integer', 'min:1'],
            'target_slot_type' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $membership = $league->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $pick = SlatePick::where('id', $validated['pick_id'])
            ->where('league_membership_id', $membership->id)
            ->with('pickSelection')
            ->first();

        if (!$pick) {
            return response()->json(['message' => 'Pick not found.'], 404);
        }

        // Block swap if locked OR if the game has already started
        $gameStarted = $pick->pickSelection && $pick->pickSelection->game_time && $pick->pickSelection->game_time->lte(now());
        if ($pick->is_locked || $gameStarted) {
            return response()->json(['message' => 'Cannot swap a pick whose game has started.'], 422);
        }

        $targetSlotType = $validated['target_slot_type'] ?? $pick->slot_type;

        // Check if target slot is occupied
        $targetPick = SlatePick::where('league_membership_id', $membership->id)
            ->where('slate_pool_id', $pick->slate_pool_id)
            ->where('position', $validated['target_position'])
            ->where('slot_type', $targetSlotType)
            ->where('slot_number', $validated['target_slot'])
            ->first();

        if ($targetPick) {
            $targetPick->load('pickSelection');
            $targetGameStarted = $targetPick->pickSelection && $targetPick->pickSelection->game_time && $targetPick->pickSelection->game_time->lte(now());
            if ($targetPick->is_locked || $targetGameStarted) {
                return response()->json(['message' => 'Cannot swap with a pick whose game has started.'], 422);
            }

            // Swap the two picks
            $originalPosition = $pick->position;
            $originalSlot = $pick->slot_number;
            $originalSlotType = $pick->slot_type;

            $targetPick->update([
                'position' => $originalPosition,
                'slot_number' => $originalSlot,
                'slot_type' => $originalSlotType,
            ]);
        }

        $pick->update([
            'position' => $validated['target_position'],
            'slot_number' => $validated['target_slot'],
            'slot_type' => $targetSlotType,
        ]);

        return response()->json(['message' => 'Pick swapped successfully.']);
    }

    /**
     * Trigger a manual odds refresh (rate-limited).
     */
    public function refreshOdds(Request $request, League $league): JsonResponse
    {
        $user = $request->user();
        $membership = $league->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $week = $league->current_week;
        $slatePool = SlatePool::where('league_id', $league->id)
            ->where('week', $week)
            ->first();

        if (!$slatePool) {
            return response()->json(['message' => 'No active slate pool.'], 404);
        }

        // Rate limit: once per 15 minutes per user per league
        $cacheKey = "odds_refresh.{$user->id}.{$league->id}";
        if (cache()->has($cacheKey)) {
            return response()->json(['message' => 'Odds were recently refreshed. Try again later.'], 429);
        }

        // Mark as refreshed (15 min cooldown)
        cache()->put($cacheKey, true, now()->addMinutes(15));

        // Get active pick selections (pre-game only — no API calls for live games)
        $selections = $slatePool->pickSelections()
            ->where('outcome', 'pending')
            ->where('is_drafted', true)
            ->where('game_time', '>', now())
            ->get();

        if ($selections->isEmpty()) {
            return response()->json(['message' => 'No picks to refresh.']);
        }

        // Group by sport and fetch updated odds
        $oddsApi = app(\App\Services\OddsApiService::class);
        $grouped = $selections->groupBy('sport');
        $refreshed = 0;

        foreach ($grouped as $sport => $sportSelections) {
            $eventIds = $sportSelections->pluck('external_id')
                ->map(fn ($id) => explode('_', $id)[0])
                ->unique()
                ->values()
                ->toArray();

            $result = $oddsApi->fetchCurrentOddsMap($eventIds, $sport);
            $oddsMap = $result['odds'];
            $pointsMap = $result['points'];

            foreach ($sportSelections as $selection) {
                if (!isset($oddsMap[$selection->external_id])) {
                    continue;
                }

                // Skip player props if the bookmaker has shifted the line
                if ($selection->pick_type === 'player_prop') {
                    $apiPoint = $pointsMap[$selection->external_id] ?? null;
                    if ($apiPoint !== null && preg_match('/(?:Over|Under)\s+([\d.]+)/i', $selection->description, $m)) {
                        $originalPoint = (float) $m[1];
                        if (abs($apiPoint - $originalPoint) > 0.01) {
                            continue;
                        }
                    }
                }

                $selection->update([
                    'current_odds' => $oddsMap[$selection->external_id],
                    'odds_updated_at' => now(),
                ]);
                $refreshed++;
            }
        }

        return response()->json([
            'data' => [
                'refreshed' => $refreshed,
                'message' => "Refreshed odds for {$refreshed} picks.",
            ],
        ]);
    }
}
