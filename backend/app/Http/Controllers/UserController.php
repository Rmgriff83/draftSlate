<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function careerStats(Request $request, User $user): JsonResponse
    {
        return response()->json([
            'display_name' => $user->display_name,
            'avatar_url' => $user->avatar_url,
            'career_picks_graded' => $user->career_picks_graded,
            'career_picks_hit' => $user->career_picks_hit,
            'career_moneyline_hits' => $user->career_moneyline_hits,
            'career_spread_hits' => $user->career_spread_hits,
            'career_total_hits' => $user->career_total_hits,
            'career_player_prop_hits' => $user->career_player_prop_hits,
            'career_gold_medals' => $user->career_gold_medals,
            'career_silver_medals' => $user->career_silver_medals,
            'career_bronze_medals' => $user->career_bronze_medals,
        ]);
    }
}
