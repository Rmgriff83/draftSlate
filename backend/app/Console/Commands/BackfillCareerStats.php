<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCareerStats extends Command
{
    protected $signature = 'career:backfill';
    protected $description = 'Backfill career stats on users from existing graded picks';

    public function handle(): int
    {
        $this->info('Resetting all user career stats to 0...');

        User::query()->update([
            'career_picks_graded' => 0,
            'career_picks_hit' => 0,
            'career_moneyline_hits' => 0,
            'career_spread_hits' => 0,
            'career_total_hits' => 0,
            'career_player_prop_hits' => 0,
        ]);

        $this->info('Aggregating graded picks...');

        // Join slate_picks → league_memberships to get user_id,
        // join pick_selections to get outcome + pick_type.
        // Group by user_id + pick_selection_id to deduplicate
        // (same pick drafted by same user in multiple leagues counts once).
        $rows = DB::table('slate_picks')
            ->join('league_memberships', 'slate_picks.league_membership_id', '=', 'league_memberships.id')
            ->join('pick_selections', 'slate_picks.pick_selection_id', '=', 'pick_selections.id')
            ->whereIn('pick_selections.outcome', ['hit', 'miss', 'push'])
            ->select([
                'league_memberships.user_id',
                'pick_selections.id as pick_selection_id',
                'pick_selections.outcome',
                'pick_selections.pick_type',
            ])
            ->groupBy('league_memberships.user_id', 'pick_selections.id', 'pick_selections.outcome', 'pick_selections.pick_type')
            ->get();

        // Accumulate per-user stats in memory
        $stats = [];

        foreach ($rows as $row) {
            $uid = $row->user_id;

            if (!isset($stats[$uid])) {
                $stats[$uid] = [
                    'career_picks_graded' => 0,
                    'career_picks_hit' => 0,
                    'career_moneyline_hits' => 0,
                    'career_spread_hits' => 0,
                    'career_total_hits' => 0,
                    'career_player_prop_hits' => 0,
                ];
            }

            $stats[$uid]['career_picks_graded']++;

            if ($row->outcome === 'hit') {
                $stats[$uid]['career_picks_hit']++;

                $typeCol = match ($row->pick_type) {
                    'moneyline' => 'career_moneyline_hits',
                    'spread' => 'career_spread_hits',
                    'total' => 'career_total_hits',
                    'player_prop' => 'career_player_prop_hits',
                    default => null,
                };

                if ($typeCol) {
                    $stats[$uid][$typeCol]++;
                }
            }
        }

        // Bulk update each user
        foreach ($stats as $userId => $counters) {
            User::where('id', $userId)->update($counters);
        }

        $this->info('Backfilled career stats for ' . count($stats) . ' users.');

        return self::SUCCESS;
    }
}
