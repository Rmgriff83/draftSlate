<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\Matchup;
use App\Services\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayoffController extends Controller
{
    public function bracket(Request $request, League $league): JsonResponse
    {
        $user = $request->user();
        $membership = $league->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $playoffMatchups = Matchup::where('league_id', $league->id)
            ->where('is_playoff', true)
            ->with(['homeTeam.user', 'awayTeam.user', 'winner'])
            ->orderBy('week')
            ->orderBy('playoff_round')
            ->get();

        // Group by week/round
        $rounds = [];
        foreach ($playoffMatchups as $matchup) {
            $roundKey = $matchup->playoff_round;
            $weekKey = $matchup->week;

            if (!isset($rounds[$weekKey])) {
                $rounds[$weekKey] = [];
            }

            if (!isset($rounds[$weekKey][$roundKey])) {
                $rounds[$weekKey][$roundKey] = [];
            }

            $isBye = $matchup->home_team_id === $matchup->away_team_id;

            $rounds[$weekKey][$roundKey][] = [
                'id' => $matchup->id,
                'is_bye' => $isBye,
                'status' => $matchup->status,
                'home_team' => [
                    'id' => $matchup->homeTeam->id,
                    'team_name' => $matchup->homeTeam->team_name,
                    'user_name' => $matchup->homeTeam->user->display_name ?? null,
                    'playoff_seed' => $matchup->homeTeam->playoff_seed,
                    'playoff_bracket' => $matchup->homeTeam->playoff_bracket,
                ],
                'away_team' => $isBye ? null : [
                    'id' => $matchup->awayTeam->id,
                    'team_name' => $matchup->awayTeam->team_name,
                    'user_name' => $matchup->awayTeam->user->display_name ?? null,
                    'playoff_seed' => $matchup->awayTeam->playoff_seed,
                    'playoff_bracket' => $matchup->awayTeam->playoff_bracket,
                ],
                'home_score' => $matchup->home_score,
                'away_score' => $matchup->away_score,
                'winner_id' => $matchup->winner_id,
                'is_tie' => $matchup->is_tie,
            ];
        }

        // Final standings
        $standings = $league->memberships()
            ->where('is_active', true)
            ->whereNotNull('final_position')
            ->with('user')
            ->orderBy('final_position')
            ->get()
            ->map(fn ($m) => [
                'membership_id' => $m->id,
                'team_name' => $m->team_name,
                'user_name' => $m->user->display_name ?? null,
                'final_position' => $m->final_position,
                'playoff_seed' => $m->playoff_seed,
            ]);

        return response()->json([
            'data' => [
                'format' => $league->playoff_format,
                'state' => $league->state,
                'playoff_start_week' => $league->getPlayoffStartWeek(),
                'rounds' => $rounds,
                'final_standings' => $standings,
            ],
        ]);
    }

    public function payouts(Request $request, League $league, PayoutService $payoutService): JsonResponse
    {
        $user = $request->user();
        $membership = $league->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $calculation = $payoutService->calculatePayouts($league);

        return response()->json([
            'data' => $calculation,
        ]);
    }
}
