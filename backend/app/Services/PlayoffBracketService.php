<?php

namespace App\Services;

use App\Jobs\SeasonCompletedJob;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Matchup;
use App\Models\Season;
use App\Events\PlayoffRoundCompleted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PlayoffBracketService
{
    public function generateBracket(League $league): void
    {
        $memberships = $league->memberships()
            ->where('is_active', true)
            ->orderBy('playoff_seed')
            ->get();

        $format = $league->playoff_format;
        $playoffStartWeek = $league->getPlayoffStartWeek();
        $qualifyCount = $league->getPlayoffTeamCount();

        $qualified = $memberships->take($qualifyCount);
        $eliminated = $memberships->slice($qualifyCount);

        // Assign final_position to non-qualifying teams (worst seed = last place)
        foreach ($eliminated as $index => $member) {
            $member->update([
                'final_position' => $qualifyCount + $index + 1,
                'playoff_bracket' => 'eliminated',
            ]);
        }

        // Mark all qualified teams as 'winners' bracket initially
        foreach ($qualified as $member) {
            $member->update(['playoff_bracket' => 'winners']);
        }

        // Create round-1 matchups per format
        match ($format) {
            'A' => $this->generateFormatA($league, $qualified, $playoffStartWeek),
            'B' => $this->generateFormatB($league, $qualified, $playoffStartWeek),
            'C' => $this->generateFormatC($league, $qualified, $playoffStartWeek),
            'D' => $this->generateFormatD($league, $memberships, $playoffStartWeek),
            default => null,
        };

        // Transition league state
        $league->update([
            'state' => 'playoffs',
            'current_week' => $playoffStartWeek,
        ]);

        $season = $league->currentSeason;
        if ($season) {
            $season->update([
                'status' => 'playoffs',
                'playoff_start_week' => $playoffStartWeek,
            ]);
        }

        Log::info("PlayoffBracketService: Generated {$format} bracket for league {$league->id}");
    }

    /**
     * Format A: Top 4 single elimination (2 weeks)
     * R1: 1v4, 2v3
     */
    private function generateFormatA(League $league, Collection $qualified, int $week): void
    {
        $seeds = $qualified->values();
        $this->createMatchup($league, $week, $seeds[0], $seeds[3], 'semifinal');
        $this->createMatchup($league, $week, $seeds[1], $seeds[2], 'semifinal');
    }

    /**
     * Format B: Top 4 + consolation (2 weeks)
     * R1: 1v4, 2v3 (same as A, but losers play for 3rd)
     */
    private function generateFormatB(League $league, Collection $qualified, int $week): void
    {
        $seeds = $qualified->values();
        $this->createMatchup($league, $week, $seeds[0], $seeds[3], 'semifinal');
        $this->createMatchup($league, $week, $seeds[1], $seeds[2], 'semifinal');
    }

    /**
     * Format C: Top 6 (3 weeks)
     * R1 (wild card): 3v6, 4v5. Seeds 1-2 get byes.
     */
    private function generateFormatC(League $league, Collection $qualified, int $week): void
    {
        $seeds = $qualified->values();

        // Bye matchups for seeds 1-2 (completed immediately)
        $this->createByeMatchup($league, $week, $seeds[0], 'wild_card');
        $this->createByeMatchup($league, $week, $seeds[1], 'wild_card');

        // Wild card matchups
        $this->createMatchup($league, $week, $seeds[2], $seeds[5], 'wild_card');
        $this->createMatchup($league, $week, $seeds[3], $seeds[4], 'wild_card');
    }

    /**
     * Format D: Full league (3 weeks)
     * Seeds 1-4 → winners bracket, 5+ → losers bracket
     * R1: Winners: 1v4, 2v3. Losers: pair remaining by seed (5v10, 6v9, 7v8 etc.)
     */
    private function generateFormatD(League $league, Collection $memberships, int $week): void
    {
        $seeds = $memberships->values();
        $winnersCount = min(4, $seeds->count());

        // Mark brackets
        for ($i = 0; $i < $seeds->count(); $i++) {
            $seeds[$i]->update([
                'playoff_bracket' => $i < $winnersCount ? 'winners' : 'losers',
            ]);
        }

        // Winners bracket R1
        if ($winnersCount >= 4) {
            $this->createMatchup($league, $week, $seeds[0], $seeds[3], 'winners_semifinal');
            $this->createMatchup($league, $week, $seeds[1], $seeds[2], 'winners_semifinal');
        }

        // Losers bracket R1 — pair by seed (highest vs lowest)
        $losers = $seeds->slice($winnersCount)->values();
        if ($losers->count() >= 2) {
            $pairs = $this->pairBySeeds($losers);
            foreach ($pairs as $pair) {
                $this->createMatchup($league, $week, $pair[0], $pair[1], 'losers_round1');
            }
        }

        // Odd losers member gets a bye
        if ($losers->count() % 2 === 1) {
            $this->createByeMatchup($league, $week, $losers->last(), 'losers_round1');
        }
    }

    private function pairBySeeds(Collection $teams): array
    {
        $count = $teams->count();
        $pairs = [];
        $pairCount = intdiv($count, 2);

        for ($i = 0; $i < $pairCount; $i++) {
            $pairs[] = [$teams[$i], $teams[$count - 1 - $i]];
        }

        return $pairs;
    }

    private function createMatchup(League $league, int $week, LeagueMembership $home, LeagueMembership $away, string $round): Matchup
    {
        return Matchup::create([
            'league_id' => $league->id,
            'week' => $week,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'is_playoff' => true,
            'playoff_round' => $round,
            'status' => 'scheduled',
        ]);
    }

    private function createByeMatchup(League $league, int $week, LeagueMembership $team, string $round): Matchup
    {
        return Matchup::create([
            'league_id' => $league->id,
            'week' => $week,
            'home_team_id' => $team->id,
            'away_team_id' => $team->id,
            'home_score' => 0,
            'away_score' => 0,
            'winner_id' => $team->id,
            'is_tie' => false,
            'is_playoff' => true,
            'playoff_round' => $round,
            'status' => 'completed',
        ]);
    }

    public function advanceBracket(League $league, int $completedWeek): void
    {
        $format = $league->playoff_format;
        $playoffStartWeek = $league->getPlayoffStartWeek();
        $playoffRound = $completedWeek - $playoffStartWeek + 1; // 1-indexed

        match ($format) {
            'A' => $this->advanceFormatA($league, $completedWeek, $playoffRound),
            'B' => $this->advanceFormatB($league, $completedWeek, $playoffRound),
            'C' => $this->advanceFormatC($league, $completedWeek, $playoffRound),
            'D' => $this->advanceFormatD($league, $completedWeek, $playoffRound),
            default => null,
        };

        event(new PlayoffRoundCompleted($league->id, $completedWeek, $format));

        Log::info("PlayoffBracketService: Advanced bracket for league {$league->id} after week {$completedWeek}");
    }

    /**
     * Format A: R1→R2 (championship). R2→finalize.
     */
    private function advanceFormatA(League $league, int $week, int $round): void
    {
        $matchups = $this->getCompletedPlayoffMatchups($league, $week);

        if ($round === 1) {
            // Semifinal → Championship
            $winners = $this->getWinners($matchups);
            $losers = $this->getLosers($matchups);

            // Losers get 3rd/4th by seed
            $this->assignPositionsBySeed($losers, 3);

            // Championship
            $nextWeek = $week + 1;
            $this->createMatchup($league, $nextWeek, $winners[0], $winners[1], 'championship');
        } elseif ($round === 2) {
            // Championship done
            $this->assignMatchupPositions($matchups, 1);
            $this->finalizeSeason($league);
        }
    }

    /**
     * Format B: R1→R2 (championship + 3rd place). R2→finalize.
     */
    private function advanceFormatB(League $league, int $week, int $round): void
    {
        $matchups = $this->getCompletedPlayoffMatchups($league, $week);

        if ($round === 1) {
            $winners = $this->getWinners($matchups);
            $losers = $this->getLosers($matchups);

            $nextWeek = $week + 1;
            $this->createMatchup($league, $nextWeek, $winners[0], $winners[1], 'championship');
            $this->createMatchup($league, $nextWeek, $losers[0], $losers[1], 'third_place');
        } elseif ($round === 2) {
            foreach ($matchups as $matchup) {
                if ($matchup->playoff_round === 'championship') {
                    $this->assignMatchupPositions(collect([$matchup]), 1);
                } elseif ($matchup->playoff_round === 'third_place') {
                    $this->assignMatchupPositions(collect([$matchup]), 3);
                }
            }
            $this->finalizeSeason($league);
        }
    }

    /**
     * Format C: R1(wild_card)→R2(semifinal)→R3(championship+consolation).
     */
    private function advanceFormatC(League $league, int $week, int $round): void
    {
        $matchups = $this->getCompletedPlayoffMatchups($league, $week);

        if ($round === 1) {
            // Wild card done → semifinals
            // Get actual played matchups (non-bye) winners
            $playedMatchups = $matchups->filter(fn ($m) => $m->home_team_id !== $m->away_team_id);
            $byeMatchups = $matchups->filter(fn ($m) => $m->home_team_id === $m->away_team_id);

            $wcWinners = $this->getWinners($playedMatchups);
            $wcLosers = $this->getLosers($playedMatchups);
            $byeTeams = $byeMatchups->map(fn ($m) => LeagueMembership::find($m->winner_id))->values();

            // Sort WC winners by seed (lowest seed number = best)
            $wcWinners = collect($wcWinners)->sortBy('playoff_seed')->values();
            $byeTeams = $byeTeams->sortBy('playoff_seed')->values();

            $nextWeek = $week + 1;

            // Semis: seed 1 vs lowest remaining, seed 2 vs other
            $this->createMatchup($league, $nextWeek, $byeTeams[0], $wcWinners->last(), 'semifinal');
            $this->createMatchup($league, $nextWeek, $byeTeams[1], $wcWinners->first(), 'semifinal');

            // Consolation: WC losers play for 5th place
            if (count($wcLosers) >= 2) {
                $this->createMatchup($league, $nextWeek, $wcLosers[0], $wcLosers[1], 'consolation_semi');
            }
        } elseif ($round === 2) {
            $nextWeek = $week + 1;
            $semiMatchups = $matchups->filter(fn ($m) => $m->playoff_round === 'semifinal');
            $consolMatchups = $matchups->filter(fn ($m) => $m->playoff_round === 'consolation_semi');

            $semiWinners = $this->getWinners($semiMatchups);
            $semiLosers = $this->getLosers($semiMatchups);

            // Championship + 3rd place
            $this->createMatchup($league, $nextWeek, $semiWinners[0], $semiWinners[1], 'championship');
            $this->createMatchup($league, $nextWeek, $semiLosers[0], $semiLosers[1], 'third_place');

            // 5th place game
            if ($consolMatchups->isNotEmpty()) {
                $consolWinners = $this->getWinners($consolMatchups);
                $consolLosers = $this->getLosers($consolMatchups);
                $this->createMatchup($league, $nextWeek, $consolWinners[0], $consolLosers[0], 'fifth_place');
            }
        } elseif ($round === 3) {
            foreach ($matchups as $matchup) {
                $startPos = match ($matchup->playoff_round) {
                    'championship' => 1,
                    'third_place' => 3,
                    'fifth_place' => 5,
                    default => null,
                };
                if ($startPos) {
                    $this->assignMatchupPositions(collect([$matchup]), $startPos);
                }
            }
            $this->finalizeSeason($league);
        }
    }

    /**
     * Format D: Full league (3 rounds)
     */
    private function advanceFormatD(League $league, int $week, int $round): void
    {
        $matchups = $this->getCompletedPlayoffMatchups($league, $week);

        if ($round === 1) {
            $nextWeek = $week + 1;
            $winnersMatchups = $matchups->filter(fn ($m) => $m->playoff_round === 'winners_semifinal');
            $losersMatchups = $matchups->filter(fn ($m) => in_array($m->playoff_round, ['losers_round1']));

            $wWinners = $this->getWinners($winnersMatchups);
            $wLosers = $this->getLosers($winnersMatchups);

            // Winners championship
            $this->createMatchup($league, $nextWeek, $wWinners[0], $wWinners[1], 'winners_championship');

            // Winners losers play consolation for 3rd
            $this->createMatchup($league, $nextWeek, $wLosers[0], $wLosers[1], 'winners_consolation');

            // Losers bracket advancement
            $playedLosers = $losersMatchups->filter(fn ($m) => $m->home_team_id !== $m->away_team_id);
            $byeLosers = $losersMatchups->filter(fn ($m) => $m->home_team_id === $m->away_team_id);

            $losersWinners = collect($this->getWinners($playedLosers));
            $losersEliminated = $this->getLosers($playedLosers);

            // Add bye winners to the advancing pool
            foreach ($byeLosers as $bye) {
                $losersWinners->push(LeagueMembership::find($bye->winner_id));
            }

            // Eliminated losers get positions from bottom up
            $totalTeams = $league->memberships()->where('is_active', true)->count();
            $eliminatedCount = count($losersEliminated);
            $startPos = $totalTeams - $eliminatedCount + 1;
            $this->assignPositionsBySeed($losersEliminated, $startPos);

            // Losers R2: pair remaining
            $losersWinners = $losersWinners->sortBy('playoff_seed')->values();
            if ($losersWinners->count() >= 2) {
                $pairs = $this->pairBySeeds($losersWinners);
                foreach ($pairs as $pair) {
                    $this->createMatchup($league, $nextWeek, $pair[0], $pair[1], 'losers_round2');
                }
                if ($losersWinners->count() % 2 === 1) {
                    $this->createByeMatchup($league, $nextWeek, $losersWinners->last(), 'losers_round2');
                }
            } elseif ($losersWinners->count() === 1) {
                // Only one left, they get a bye
                $this->createByeMatchup($league, $nextWeek, $losersWinners[0], 'losers_round2');
            }
        } elseif ($round === 2) {
            $nextWeek = $week + 1;
            $championshipMatchups = $matchups->filter(fn ($m) => $m->playoff_round === 'winners_championship');
            $consolationMatchups = $matchups->filter(fn ($m) => $m->playoff_round === 'winners_consolation');
            $losersMatchups = $matchups->filter(fn ($m) => $m->playoff_round === 'losers_round2');

            // Winners bracket positions
            $this->assignMatchupPositions($championshipMatchups, 1);
            $this->assignMatchupPositions($consolationMatchups, 3);

            // Losers bracket: assign positions to losers, winners get 5th onwards
            $playedLosers = $losersMatchups->filter(fn ($m) => $m->home_team_id !== $m->away_team_id);
            $byeLosers = $losersMatchups->filter(fn ($m) => $m->home_team_id === $m->away_team_id);

            // Get already-positioned teams count to figure out remaining positions
            $positionedCount = $league->memberships()
                ->where('is_active', true)
                ->whereNotNull('final_position')
                ->count();

            $totalActive = $league->memberships()->where('is_active', true)->count();
            $losersInRound = collect($this->getLosers($playedLosers));

            // Assign remaining positions from bottom up for round 2 losers
            $remainingSlots = $totalActive - $positionedCount;
            $nextPos = $positionedCount + 1;

            // Losers of losers R2 get higher position numbers (worse finish)
            $advancers = collect($this->getWinners($playedLosers));
            foreach ($byeLosers as $bye) {
                $advancers->push(LeagueMembership::find($bye->winner_id));
            }

            // Assign final positions to losers R2 losers
            $losersInRound = $losersInRound->sortBy('playoff_seed')->values();
            $loserStartPos = $totalActive - $losersInRound->count() + 1;
            // Only assign if they don't already have a position
            foreach ($losersInRound as $i => $member) {
                if (!$member->final_position) {
                    $assignPos = max($loserStartPos + $i, 5);
                    $member->update(['final_position' => $assignPos]);
                }
            }

            // Assign positions to remaining losers bracket winners (5th onward)
            $advancers = $advancers->sortBy('playoff_seed')->values();
            $advancerStartPos = 5;
            foreach ($advancers as $i => $member) {
                if (!$member->final_position) {
                    $member->update(['final_position' => $advancerStartPos + $i]);
                }
            }

            $this->finalizeSeason($league);
        } elseif ($round === 3) {
            // Shouldn't normally reach here for format D, but handle gracefully
            $this->finalizeSeason($league);
        }
    }

    private function getCompletedPlayoffMatchups(League $league, int $week): Collection
    {
        return Matchup::where('league_id', $league->id)
            ->where('week', $week)
            ->where('is_playoff', true)
            ->where('status', 'completed')
            ->get();
    }

    private function getWinners(Collection $matchups): array
    {
        return $matchups
            ->filter(fn ($m) => $m->home_team_id !== $m->away_team_id) // skip byes
            ->map(fn ($m) => LeagueMembership::find($m->winner_id))
            ->filter()
            ->values()
            ->toArray();
    }

    private function getLosers(Collection $matchups): array
    {
        return $matchups
            ->filter(fn ($m) => $m->home_team_id !== $m->away_team_id)
            ->map(function ($m) {
                $loserId = $m->winner_id === $m->home_team_id
                    ? $m->away_team_id
                    : $m->home_team_id;
                return LeagueMembership::find($loserId);
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Assign final_position to winner and loser of each matchup.
     * Winner gets $startPos, loser gets $startPos+1.
     */
    private function assignMatchupPositions(Collection $matchups, int $startPos): void
    {
        foreach ($matchups as $matchup) {
            if ($matchup->home_team_id === $matchup->away_team_id) {
                continue; // bye
            }

            $winnerId = $matchup->winner_id;
            $loserId = $winnerId === $matchup->home_team_id
                ? $matchup->away_team_id
                : $matchup->home_team_id;

            LeagueMembership::where('id', $winnerId)
                ->whereNull('final_position')
                ->update(['final_position' => $startPos]);
            LeagueMembership::where('id', $loserId)
                ->whereNull('final_position')
                ->update(['final_position' => $startPos + 1]);
        }
    }

    /**
     * Assign positions to a list of teams by seed (best seed gets best position).
     */
    private function assignPositionsBySeed(array $teams, int $startPos): void
    {
        $sorted = collect($teams)->sortBy('playoff_seed')->values();
        foreach ($sorted as $i => $member) {
            $member->update(['final_position' => $startPos + $i]);
        }
    }

    public function finalizeSeason(League $league): void
    {
        // Ensure all active memberships have a final_position
        $unpositioned = $league->memberships()
            ->where('is_active', true)
            ->whereNull('final_position')
            ->orderBy('playoff_seed')
            ->get();

        $maxPos = $league->memberships()
            ->where('is_active', true)
            ->max('final_position') ?? 0;

        foreach ($unpositioned as $member) {
            $maxPos++;
            $member->update(['final_position' => $maxPos]);
        }

        $league->update(['state' => 'completed']);

        $season = $league->currentSeason;
        if ($season) {
            $season->update(['status' => 'completed']);
        }

        SeasonCompletedJob::dispatch($league->id);

        Log::info("PlayoffBracketService: Season finalized for league {$league->id}");
    }

    /**
     * Resolve a playoff tie: higher seed (lower playoff_seed number) wins.
     */
    public static function resolvePlayoffTie(Matchup $matchup): int
    {
        $home = LeagueMembership::find($matchup->home_team_id);
        $away = LeagueMembership::find($matchup->away_team_id);

        // Lower playoff_seed = better seed = wins tiebreak
        return ($home->playoff_seed <= $away->playoff_seed)
            ? $matchup->home_team_id
            : $matchup->away_team_id;
    }
}
