<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StandingsController extends Controller
{
    public function index(Request $request, League $league, ScoringService $scoring): JsonResponse
    {
        $user = $request->user();
        $membership = $league->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        $rankings = $scoring->calculateRankings($league);

        $standings = $rankings->map(function ($member, $index) use ($membership, $league) {
            $totalGames = $member->wins + $member->losses + $member->ties;

            return [
                'rank' => $index + 1,
                'membership_id' => $member->id,
                'user_id' => $member->user_id,
                'team_name' => $member->team_name,
                'user_name' => $member->user->name ?? null,
                'wins' => $member->wins,
                'losses' => $member->losses,
                'ties' => $member->ties,
                'total_correct_picks' => $member->total_correct_picks,
                'playoff_seed' => $member->playoff_seed,
                'is_current_user' => $member->id === $membership->id,
                'win_percentage' => $totalGames > 0
                    ? round(($member->wins + ($member->ties * 0.5)) / $totalGames, 3)
                    : 0,
            ];
        });

        return response()->json([
            'data' => $standings->values(),
        ]);
    }
}
