<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Matchup;
use App\Models\PickSelection;
use App\Models\SlatePick;
use App\Services\PlayoffBracketService;
use Illuminate\Support\Collection;

class ScoringService
{
    public function __construct(private OddsMathService $oddsMath) {}

    /**
     * Grade a pick using its description + result_data (scores/stats from OddsRefreshJob).
     */
    public function gradePick(PickSelection $pick): void
    {
        if ($pick->outcome !== 'pending' || $pick->result_data === null) {
            return;
        }

        $rd = $pick->result_data;
        $gameComplete = ($rd['completed'] === true)
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

    private function gradeMoneyline(PickSelection $pick, array $rd): ?string
    {
        $homeScore = $rd['home_score'] ?? null;
        $awayScore = $rd['away_score'] ?? null;
        $homeTeam = $rd['home_team'] ?? $pick->home_team ?? '';
        $awayTeam = $rd['away_team'] ?? $pick->away_team ?? '';

        if ($homeScore === null || $awayScore === null) {
            return null; // Scores not available yet — retry later
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

    private function gradeSpread(PickSelection $pick, array $rd): ?string
    {
        $homeScore = $rd['home_score'] ?? null;
        $awayScore = $rd['away_score'] ?? null;
        $homeTeam = $rd['home_team'] ?? $pick->home_team ?? '';

        if ($homeScore === null || $awayScore === null) {
            return null; // Scores not available yet — retry later
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

    private function gradeTotal(PickSelection $pick, array $rd): ?string
    {
        $homeScore = $rd['home_score'] ?? null;
        $awayScore = $rd['away_score'] ?? null;

        if ($homeScore === null || $awayScore === null) {
            return null; // Scores not available yet — retry later
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
        // Void if player was scratched (inactive) — distinct from DNP (coach's decision)
        $reason = $rd['player_stats']['not_playing_reason'] ?? null;
        if ($reason !== null && str_starts_with(strtoupper($reason), 'INACTIVE')) {
            return 'void';
        }

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

        // Stats only go up, so some outcomes are definitive mid-game:
        // - Over clearing the line is a definitive HIT
        // - Under exceeding the line is a definitive MISS
        if ($direction === 'over' && $actual > $line) {
            return 'hit';
        }
        if ($direction === 'under' && $actual > $line) {
            return 'miss';
        }

        // Remaining cases (under still alive, over not yet cleared) need game completion
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

        // Overall Odds bonus: +1 to the team with lower aggregate implied probability (riskier)
        $homeProb = $this->getStarterAggregateProb($matchup->home_team_id, $matchup->week);
        $awayProb = $this->getStarterAggregateProb($matchup->away_team_id, $matchup->week);

        if ($homeProb !== null && $awayProb !== null && abs($homeProb - $awayProb) > 0.0001) {
            if ($homeProb < $awayProb) {
                $homeScore++;
            } else {
                $awayScore++;
            }
        }

        $isTie = $homeScore === $awayScore;
        $winnerId = $isTie ? null : ($homeScore > $awayScore ? $matchup->home_team_id : $matchup->away_team_id);

        // Playoff tie resolution: higher seed wins
        if ($isTie && $matchup->is_playoff) {
            $winnerId = PlayoffBracketService::resolvePlayoffTie($matchup);
            $isTie = false;
        }

        $matchup->update([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'winner_id' => $winnerId,
            'is_tie' => $isTie,
            'status' => 'completed',
        ]);
    }

    /**
     * Get the average implied probability of a team's locked starters for a given week.
     * Returns null if any starter is not locked (bonus not applicable yet).
     */
    public function getStarterAggregateProb(int $membershipId, int $week): ?float
    {
        $starters = SlatePick::where('league_membership_id', $membershipId)
            ->where('week', $week)
            ->where('position', 'starter')
            ->get();

        if ($starters->isEmpty()) {
            return null;
        }

        $lockedOddsList = [];
        foreach ($starters as $pick) {
            if (!$pick->is_locked || $pick->locked_odds === null) {
                return null; // Not all starters locked — bonus not applicable
            }
            $lockedOddsList[] = $pick->locked_odds;
        }

        return $this->oddsMath->calculateAggregateImpliedProbability($lockedOddsList);
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
        $members = $league->memberships()->where('is_active', true)->get();

        foreach ($members as $member) {
            $matchups = Matchup::where('league_id', $league->id)
                ->where('status', 'completed')
                ->where('is_playoff', false)
                ->where(function ($q) use ($member) {
                    $q->where('home_team_id', $member->id)
                        ->orWhere('away_team_id', $member->id);
                })
                ->get();

            $wins = 0;
            $losses = 0;
            $ties = 0;
            $totalCorrect = 0;
            $totalOppCorrect = 0;

            foreach ($matchups as $matchup) {
                $isHome = $matchup->home_team_id === $member->id;

                if ($matchup->is_tie) {
                    $ties++;
                } elseif ($matchup->winner_id === $member->id) {
                    $wins++;
                } else {
                    $losses++;
                }

                $myScore = $isHome ? ($matchup->home_score ?? 0) : ($matchup->away_score ?? 0);
                $oppScore = $isHome ? ($matchup->away_score ?? 0) : ($matchup->home_score ?? 0);
                $totalCorrect += $myScore;
                $totalOppCorrect += $oppScore;
            }

            $member->update([
                'wins' => $wins,
                'losses' => $losses,
                'ties' => $ties,
                'total_correct_picks' => $totalCorrect,
                'total_opponent_correct_picks' => $totalOppCorrect,
            ]);
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
