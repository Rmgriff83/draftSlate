<?php

namespace App\Services;

use App\Models\League;
use App\Models\Matchup;
use Illuminate\Support\Collection;

class MatchupSchedulerService
{
    public function generateSeasonSchedule(League $league): void
    {
        $memberships = $league->memberships()->where('is_active', true)->orderBy('id')->get();
        $count = $memberships->count();

        if ($count < 2) {
            return;
        }

        $ids = $memberships->pluck('id')->values()->toArray();
        $totalWeeks = $league->total_matchups ?? ($count - 1);

        // Circle method round-robin: fix first team, rotate the rest
        $fixed = $ids[0];
        $rotating = array_slice($ids, 1);

        // Pad with null for bye if odd number of teams
        if ($count % 2 !== 0) {
            $rotating[] = null;
        }

        $rotatingCount = count($rotating);
        $roundRobinLength = $rotatingCount; // N-1 rounds for N teams (or N for odd)

        $matchups = [];

        for ($week = 1; $week <= $totalWeeks; $week++) {
            $cycle = ($week - 1) % $roundRobinLength;
            $flip = (int) floor(($week - 1) / $roundRobinLength) % 2 === 1;

            // Build the rotation for this round
            $rotated = $this->rotateArray($rotating, $cycle);

            // Generate pairings
            $pairs = [];

            // First pairing: fixed vs first in rotated
            if ($rotated[0] !== null) {
                $home = $flip ? $rotated[0] : $fixed;
                $away = $flip ? $fixed : $rotated[0];
                $pairs[] = [$home, $away];
            }

            // Remaining pairings: fold the rotated array
            for ($i = 1; $i < ceil($rotatingCount / 2) + ($rotatingCount % 2 === 0 ? 0 : 0); $i++) {
                $j = $rotatingCount - $i;
                if ($i >= $j) {
                    break;
                }

                $teamA = $rotated[$i];
                $teamB = $rotated[$j];

                if ($teamA === null || $teamB === null) {
                    continue;
                }

                $home = $flip ? $teamB : $teamA;
                $away = $flip ? $teamA : $teamB;
                $pairs[] = [$home, $away];
            }

            foreach ($pairs as [$homeId, $awayId]) {
                $matchups[] = [
                    'league_id' => $league->id,
                    'week' => $week,
                    'home_team_id' => $homeId,
                    'away_team_id' => $awayId,
                    'status' => $week === 1 ? 'in_progress' : 'scheduled',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Bulk insert
        foreach (array_chunk($matchups, 100) as $chunk) {
            Matchup::insert($chunk);
        }
    }

    public function generateWeekMatchups(League $league, int $week): void
    {
        $memberships = $league->memberships()->where('is_active', true)->orderBy('id')->get();
        $count = $memberships->count();

        if ($count < 2) {
            return;
        }

        $ids = $memberships->pluck('id')->values()->toArray();

        $fixed = $ids[0];
        $rotating = array_slice($ids, 1);

        if ($count % 2 !== 0) {
            $rotating[] = null;
        }

        $rotatingCount = count($rotating);
        $roundRobinLength = $rotatingCount;

        $cycle = ($week - 1) % $roundRobinLength;
        $flip = (int) floor(($week - 1) / $roundRobinLength) % 2 === 1;

        $rotated = $this->rotateArray($rotating, $cycle);

        // First pairing
        if ($rotated[0] !== null) {
            $home = $flip ? $rotated[0] : $fixed;
            $away = $flip ? $fixed : $rotated[0];
            Matchup::create([
                'league_id' => $league->id,
                'week' => $week,
                'home_team_id' => $home,
                'away_team_id' => $away,
                'status' => 'in_progress',
            ]);
        }

        for ($i = 1; $i < $rotatingCount; $i++) {
            $j = $rotatingCount - $i;
            if ($i >= $j) {
                break;
            }

            $teamA = $rotated[$i];
            $teamB = $rotated[$j];

            if ($teamA === null || $teamB === null) {
                continue;
            }

            $home = $flip ? $teamB : $teamA;
            $away = $flip ? $teamA : $teamB;
            Matchup::create([
                'league_id' => $league->id,
                'week' => $week,
                'home_team_id' => $home,
                'away_team_id' => $away,
                'status' => 'in_progress',
            ]);
        }
    }

    private function rotateArray(array $arr, int $positions): array
    {
        if (empty($arr)) {
            return $arr;
        }

        $positions = $positions % count($arr);

        return array_merge(
            array_slice($arr, $positions),
            array_slice($arr, 0, $positions),
        );
    }
}
