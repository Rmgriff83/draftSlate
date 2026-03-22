<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Matchup;
use App\Models\PickSelection;
use App\Models\SlatePick;
use Illuminate\Support\Collection;

class ScoringService
{
    /**
     * Grade a pick using its description + result_data (scores/stats from OddsRefreshJob).
     */
    public function gradePick(PickSelection $pick): void
    {
        if ($pick->outcome !== 'pending' || $pick->result_data === null) {
            return;
        }

        $rd = $pick->result_data;
        $gameComplete = !empty($rd['completed'])
            || str_starts_with(strtolower($rd['game_status'] ?? ''), 'final');

        // If the game was cancelled or postponed
        if (($rd['game_status'] ?? null) === 'cancelled') {
            $pick->update(['outcome' => 'void']);
            return;
        }

        // Player props can be graded mid-game (Over bets clear the line)
        if ($pick->pick_type === 'player_prop') {
            $outcome = $this->gradePlayerProp($pick, $rd, $gameComplete);
        } elseif ($gameComplete) {
            // All other pick types require game completion
            $outcome = match ($pick->pick_type) {
                'moneyline' => $this->gradeMoneyline($pick, $rd),
                'spread' => $this->gradeSpread($pick, $rd),
                'total' => $this->gradeTotal($pick, $rd),
                default => 'void',
            };
        } else {
            return; // Game not complete, can't grade non-prop picks
        }

        if ($outcome === null) {
            return; // Can't determine yet (e.g. Under prop mid-game)
        }

        $pick->update(['outcome' => $outcome]);
    }

    private function gradeMoneyline(PickSelection $pick, array $rd): string
    {
        $homeScore = $rd['home_score'] ?? null;
        $awayScore = $rd['away_score'] ?? null;
        $homeTeam = $rd['home_team'] ?? $pick->home_team ?? '';
        $awayTeam = $rd['away_team'] ?? $pick->away_team ?? '';

        if ($homeScore === null || $awayScore === null) {
            return 'void';
        }

        // Parse picked team from description: "Team Name ML (...)"
        if (!preg_match('/^(.+?)\s+ML/i', $pick->description, $m)) {
            return 'void';
        }
        $pickedTeam = trim($m[1]);

        if ($homeScore === $awayScore) {
            return 'push';
        }

        $isHome = $pickedTeam === $homeTeam || str_contains($homeTeam, $pickedTeam);
        $pickedWon = $isHome ? ($homeScore > $awayScore) : ($awayScore > $homeScore);

        return $pickedWon ? 'hit' : 'miss';
    }

    private function gradeSpread(PickSelection $pick, array $rd): string
    {
        $homeScore = $rd['home_score'] ?? null;
        $awayScore = $rd['away_score'] ?? null;
        $homeTeam = $rd['home_team'] ?? $pick->home_team ?? '';

        if ($homeScore === null || $awayScore === null) {
            return 'void';
        }

        // Parse: "Team Name +/-X.X (...)"
        if (!preg_match('/^(.+?)\s+([+-]?[\d.]+)\s/i', $pick->description, $m)) {
            return 'void';
        }
        $pickedTeam = trim($m[1]);
        $spreadLine = (float) $m[2];

        $isHome = $pickedTeam === $homeTeam || str_contains($homeTeam, $pickedTeam);
        $margin = $isHome ? ($homeScore - $awayScore) : ($awayScore - $homeScore);
        $adjusted = $margin + $spreadLine;

        if ($adjusted > 0) return 'hit';
        if ($adjusted == 0) return 'push';
        return 'miss';
    }

    private function gradeTotal(PickSelection $pick, array $rd): string
    {
        $homeScore = $rd['home_score'] ?? null;
        $awayScore = $rd['away_score'] ?? null;

        if ($homeScore === null || $awayScore === null) {
            return 'void';
        }

        // Parse: "Over/Under X.X (...)"
        if (!preg_match('/^(Over|Under)\s+([\d.]+)/i', $pick->description, $m)) {
            return 'void';
        }
        $direction = strtolower($m[1]);
        $line = (float) $m[2];
        $actual = $homeScore + $awayScore;

        if ($actual == $line) return 'push';
        if ($direction === 'over' && $actual > $line) return 'hit';
        if ($direction === 'under' && $actual < $line) return 'hit';
        return 'miss';
    }

    private function gradePlayerProp(PickSelection $pick, array $rd, bool $gameComplete): ?string
    {
        // Use current_stat from result_data (populated by OddsRefreshJob via SportsDataService)
        $actual = $rd['current_stat'] ?? null;

        if ($actual === null) {
            // Only void if game is complete AND we actually received box score data
            // (player_stats present means the API was reachable but this player wasn't found)
            // Without player_stats, the box score API may not have caught up yet — wait.
            if ($gameComplete && !empty($rd['player_stats'])) {
                return 'void';
            }
            return null;
        }

        // Parse: "Player Over/Under X.X stat"
        if (!preg_match('/(Over|Under)\s+([\d.]+)/i', $pick->description, $m)) {
            return $gameComplete ? 'void' : null;
        }
        $direction = strtolower($m[1]);
        $line = (float) $m[2];

        // "Over" bets: stats only go up, so clearing the line is a definitive HIT
        if ($direction === 'over' && $actual > $line) {
            return 'hit';
        }

        // Can only determine miss/push/under-hit at game completion
        if (!$gameComplete) {
            return null;
        }

        if ($actual == $line) return 'push';
        if ($direction === 'under' && $actual < $line) return 'hit';
        return 'miss';
    }

    public function scoreMatchup(Matchup $matchup): void
    {
        $matchup->load(['homeTeam.slatePicks.pickSelection', 'awayTeam.slatePicks.pickSelection']);

        $homeScore = $this->countHits($matchup->homeTeam, $matchup->league_id, $matchup->week);
        $awayScore = $this->countHits($matchup->awayTeam, $matchup->league_id, $matchup->week);

        $isTie = $homeScore === $awayScore;
        $winnerId = $isTie ? null : ($homeScore > $awayScore ? $matchup->home_team_id : $matchup->away_team_id);

        $matchup->update([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'winner_id' => $winnerId,
            'is_tie' => $isTie,
            'status' => 'completed',
        ]);
    }

    private function countHits(LeagueMembership $membership, int $leagueId, int $week): int
    {
        return SlatePick::where('league_membership_id', $membership->id)
            ->where('week', $week)
            ->where('position', 'starter')
            ->whereHas('pickSelection', function ($q) {
                $q->where('outcome', 'hit');
            })
            ->count();
    }

    public function updateStandings(League $league, int $week): void
    {
        $matchups = Matchup::where('league_id', $league->id)
            ->where('week', $week)
            ->where('status', 'completed')
            ->get();

        foreach ($matchups as $matchup) {
            if ($matchup->is_tie) {
                LeagueMembership::where('id', $matchup->home_team_id)
                    ->increment('ties');
                LeagueMembership::where('id', $matchup->away_team_id)
                    ->increment('ties');
            } else {
                $loserId = $matchup->winner_id === $matchup->home_team_id
                    ? $matchup->away_team_id
                    : $matchup->home_team_id;

                LeagueMembership::where('id', $matchup->winner_id)->increment('wins');
                LeagueMembership::where('id', $loserId)->increment('losses');
            }

            // Update correct picks totals
            $homeHits = $matchup->home_score ?? 0;
            $awayHits = $matchup->away_score ?? 0;

            LeagueMembership::where('id', $matchup->home_team_id)
                ->increment('total_correct_picks', $homeHits);
            LeagueMembership::where('id', $matchup->away_team_id)
                ->increment('total_correct_picks', $awayHits);

            // Track opponent correct picks for tiebreaker
            LeagueMembership::where('id', $matchup->home_team_id)
                ->increment('total_opponent_correct_picks', $awayHits);
            LeagueMembership::where('id', $matchup->away_team_id)
                ->increment('total_opponent_correct_picks', $homeHits);
        }
    }

    public function calculateRankings(League $league): Collection
    {
        $memberships = $league->memberships()
            ->where('is_active', true)
            ->with('user')
            ->get();

        return $memberships->sort(function ($a, $b) use ($league) {
            // 1. Win percentage (higher is better)
            $aWinPct = $this->winPercentage($a);
            $bWinPct = $this->winPercentage($b);
            if ($aWinPct !== $bWinPct) {
                return $bWinPct <=> $aWinPct;
            }

            // 2. Head-to-head record
            $h2h = $this->getHeadToHeadRecord($a->id, $b->id, $league->id);
            if ($h2h['wins'] !== $h2h['losses']) {
                return $h2h['losses'] <=> $h2h['wins']; // More wins = ranked higher
            }

            // 3. Total correct picks (higher is better)
            if ($a->total_correct_picks !== $b->total_correct_picks) {
                return $b->total_correct_picks <=> $a->total_correct_picks;
            }

            // 4. Total opponent correct picks (lower is better — strength of schedule)
            if ($a->total_opponent_correct_picks !== $b->total_opponent_correct_picks) {
                return $a->total_opponent_correct_picks <=> $b->total_opponent_correct_picks;
            }

            // 5. Deterministic random
            return md5($a->id . $league->id) <=> md5($b->id . $league->id);
        })->values();
    }

    private function winPercentage(LeagueMembership $m): float
    {
        $total = $m->wins + $m->losses + $m->ties;

        if ($total === 0) {
            return 0.0;
        }

        return ($m->wins + ($m->ties * 0.5)) / $total;
    }

    public function getHeadToHeadRecord(int $teamAId, int $teamBId, int $leagueId): array
    {
        $matchups = Matchup::where('league_id', $leagueId)
            ->where('status', 'completed')
            ->where(function ($q) use ($teamAId, $teamBId) {
                $q->where(function ($q2) use ($teamAId, $teamBId) {
                    $q2->where('home_team_id', $teamAId)->where('away_team_id', $teamBId);
                })->orWhere(function ($q2) use ($teamAId, $teamBId) {
                    $q2->where('home_team_id', $teamBId)->where('away_team_id', $teamAId);
                });
            })
            ->get();

        $wins = 0;
        $losses = 0;
        $ties = 0;

        foreach ($matchups as $matchup) {
            if ($matchup->is_tie) {
                $ties++;
            } elseif ($matchup->winner_id === $teamAId) {
                $wins++;
            } else {
                $losses++;
            }
        }

        return compact('wins', 'losses', 'ties');
    }
}
