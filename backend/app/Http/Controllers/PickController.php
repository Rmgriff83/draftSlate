<?php

namespace App\Http\Controllers;

use App\Models\LeagueMembership;
use App\Models\OddsSnapshot;
use App\Models\PickSelection;
use App\Models\SlatePick;
use App\Services\PlayerGameLogService;
use App\Services\TeamGameLogService;
use Illuminate\Http\JsonResponse;

class PickController extends Controller
{
    public function study(PickSelection $pickSelection, PlayerGameLogService $gameLogService, TeamGameLogService $teamGameLogService): JsonResponse
    {
        $pick = [
            'id' => $pickSelection->id,
            'description' => $pickSelection->description,
            'pick_type' => $pickSelection->pick_type,
            'category' => $pickSelection->category,
            'player_name' => $pickSelection->player_name,
            'sport' => $pickSelection->sport,
            'snapshot_odds' => $pickSelection->snapshot_odds,
            'current_odds' => $pickSelection->current_odds,
            'game_display' => $pickSelection->game_display,
            'game_time' => $pickSelection->game_time,
        ];

        $study = ['stats_available' => false];

        $supportedSports = ['basketball_nba', 'baseball_mlb'];

        if ($pickSelection->pick_type === 'player_prop' && in_array($pickSelection->sport, $supportedSports)) {
            $description = $pickSelection->description ?? '';

            // Extract threshold and side from description (e.g. "Over 22.5")
            $threshold = null;
            $side = 'Over';
            if (preg_match('/(Over|Under)\s+([\d.]+)/i', $description, $matches)) {
                $side = ucfirst(strtolower($matches[1]));
                $threshold = (float) $matches[2];
            }

            if ($threshold !== null && $pickSelection->player_name && $pickSelection->category) {
                $data = $gameLogService->getPlayerStudyData(
                    $pickSelection->player_name,
                    $pickSelection->sport,
                    $pickSelection->category,
                    $threshold,
                    $side
                );

                if ($data !== null) {
                    $study = array_merge($data, [
                        'threshold' => $threshold,
                        'side' => $side,
                        'stat_label' => $gameLogService->categoryLabel($pickSelection->category),
                    ]);
                }
            }
        } elseif (in_array($pickSelection->pick_type, ['moneyline', 'spread', 'total'])
            && in_array($pickSelection->sport, $supportedSports)) {
            $data = $teamGameLogService->getTeamStudyData(
                $pickSelection->home_team,
                $pickSelection->away_team,
                $pickSelection->sport,
                $pickSelection->pick_type,
                $pickSelection->description ?? ''
            );

            if ($data !== null) {
                $study = $data;
            }
        }

        return response()->json([
            'pick' => $pick,
            'study' => $study,
        ]);
    }

    public function oddsHistory(PickSelection $pickSelection): JsonResponse
    {
        // Authorize: user must have a slate_pick referencing this pick_selection
        $slatePick = SlatePick::where('pick_selection_id', $pickSelection->id)
            ->whereHas('membership', fn ($q) => $q->where('user_id', auth()->id()))
            ->first();

        if (!$slatePick) {
            // Fallback: allow access if user is a member of the league that owns this pick's pool
            // (e.g. during draft when the pick hasn't been drafted yet)
            $leagueId = $pickSelection->slatePool?->league_id;
            $isMember = $leagueId && LeagueMembership::where('league_id', $leagueId)
                ->where('user_id', auth()->id())
                ->where('is_active', true)
                ->exists();

            if (!$isMember) {
                abort(403);
            }
        }

        $snapshots = OddsSnapshot::where('pick_selection_id', $pickSelection->id)
            ->orderBy('captured_at')
            ->get(['odds', 'line', 'captured_at']);

        // Include lifecycle points (only available if pick has been drafted)
        $lifecycle = collect([
            [
                'odds' => $pickSelection->snapshot_odds,
                'label' => 'Created',
                'at' => $pickSelection->created_at?->toIso8601String(),
            ],
        ]);

        if ($slatePick) {
            $lifecycle = $lifecycle->merge([
                [
                    'odds' => $slatePick->drafted_odds,
                    'label' => 'Drafted',
                    'at' => $slatePick->created_at?->toIso8601String(),
                ],
                [
                    'odds' => $slatePick->locked_odds,
                    'label' => 'Locked',
                    'at' => $slatePick->locked_at?->toIso8601String(),
                ],
            ]);
        }

        $lifecycle = $lifecycle->filter(fn ($p) => $p['odds'] !== null)->values();

        return response()->json([
            'snapshots' => $snapshots,
            'lifecycle' => $lifecycle,
        ]);
    }
}
