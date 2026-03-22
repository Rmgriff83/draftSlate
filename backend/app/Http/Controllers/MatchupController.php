<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\Matchup;
use App\Models\SlatePick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchupController extends Controller
{
    public function index(Request $request, League $league, int $week): JsonResponse
    {
        $user = $request->user();
        $membership = $league->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $matchups = Matchup::where('league_id', $league->id)
            ->where('week', $week)
            ->with(['homeTeam.user', 'awayTeam.user', 'winner'])
            ->get();

        return response()->json([
            'data' => $matchups->map(function ($matchup) {
                return [
                    'id' => $matchup->id,
                    'week' => $matchup->week,
                    'status' => $matchup->status,
                    'home_team' => [
                        'id' => $matchup->homeTeam->id,
                        'team_name' => $matchup->homeTeam->team_name,
                        'user_name' => $matchup->homeTeam->user->name ?? null,
                    ],
                    'away_team' => [
                        'id' => $matchup->awayTeam->id,
                        'team_name' => $matchup->awayTeam->team_name,
                        'user_name' => $matchup->awayTeam->user->name ?? null,
                    ],
                    'home_score' => $matchup->home_score,
                    'away_score' => $matchup->away_score,
                    'winner_id' => $matchup->winner_id,
                    'is_tie' => $matchup->is_tie,
                ];
            }),
        ]);
    }

    public function mine(Request $request, League $league, int $week): JsonResponse
    {
        $user = $request->user();
        $membership = $league->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $matchup = Matchup::where('league_id', $league->id)
            ->where('week', $week)
            ->where(function ($q) use ($membership) {
                $q->where('home_team_id', $membership->id)
                    ->orWhere('away_team_id', $membership->id);
            })
            ->with(['homeTeam.user', 'awayTeam.user'])
            ->first();

        if (!$matchup) {
            return response()->json([
                'data' => null,
                'message' => 'No matchup found for this week.',
            ]);
        }

        $isHome = $matchup->home_team_id === $membership->id;
        $opponentId = $isHome ? $matchup->away_team_id : $matchup->home_team_id;

        // Get both teams' picks
        $myPicks = $this->getTeamPicks($membership->id, $week);
        $opponentPicks = $this->getTeamPicks($opponentId, $week);

        return response()->json([
            'data' => [
                'id' => $matchup->id,
                'week' => $matchup->week,
                'status' => $matchup->status,
                'is_home' => $isHome,
                'home_team' => [
                    'id' => $matchup->homeTeam->id,
                    'team_name' => $matchup->homeTeam->team_name,
                    'user_name' => $matchup->homeTeam->user->name ?? null,
                ],
                'away_team' => [
                    'id' => $matchup->awayTeam->id,
                    'team_name' => $matchup->awayTeam->team_name,
                    'user_name' => $matchup->awayTeam->user->name ?? null,
                ],
                'home_score' => $matchup->home_score,
                'away_score' => $matchup->away_score,
                'winner_id' => $matchup->winner_id,
                'is_tie' => $matchup->is_tie,
                'my_picks' => $myPicks,
                'opponent_picks' => $opponentPicks,
            ],
        ]);
    }

    private function getTeamPicks(int $membershipId, int $week): array
    {
        return SlatePick::where('league_membership_id', $membershipId)
            ->where('week', $week)
            ->with('pickSelection')
            ->orderBy('position')
            ->orderBy('slot_number')
            ->get()
            ->map(function ($pick) {
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
            })
            ->toArray();
    }
}
