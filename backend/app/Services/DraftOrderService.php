<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeagueMembership;

class DraftOrderService
{
    /**
     * Generate weighted random draft order for a league's weekly draft.
     * Week 1: pure random (equal weights).
     * Week 2+: teams with fewer correct picks get better odds of drafting first.
     */
    public function generateDraftOrder(League $league, int $week): array
    {
        $memberships = $league->memberships()
            ->where('is_active', true)
            ->get();

        if ($memberships->isEmpty()) {
            return ['order' => [], 'weights' => []];
        }

        $membershipIds = $memberships->pluck('id')->toArray();

        // Week 1: pure random
        if ($week <= 1) {
            shuffle($membershipIds);

            return [
                'order' => $membershipIds,
                'weights' => array_map(fn ($id) => [
                    'membership_id' => $id,
                    'prior_correct' => 0,
                    'weight' => 1.0,
                ], $membershipIds),
            ];
        }

        // Week 2+: weight by inverse of prior week's correct picks
        $priorWeek = $week - 1;
        $scores = [];

        foreach ($memberships as $membership) {
            $correctPicks = $membership->slatePicks()
                ->where('week', $priorWeek)
                ->whereHas('pickSelection', fn ($q) => $q->where('outcome', 'hit'))
                ->count();

            $scores[$membership->id] = $correctPicks;
        }

        // Invert scores: fewer correct picks = higher weight
        $maxScore = max($scores) ?: 1;
        $weights = [];

        foreach ($scores as $membershipId => $score) {
            // Weight = (maxScore + 1 - score) to ensure everyone has some weight
            $weight = $maxScore + 1 - $score;
            $weights[$membershipId] = [
                'membership_id' => $membershipId,
                'prior_correct' => $score,
                'weight' => $weight,
            ];
        }

        // Weighted random shuffle
        $order = $this->weightedShuffle($weights);

        return [
            'order' => $order,
            'weights' => array_values($weights),
        ];
    }

    /**
     * Build the full snake-order pick sequence from draft positions.
     * E.g., 4 teams [A,B,C,D], 2 rounds → [A,B,C,D, D,C,B,A]
     */
    public function buildSnakeSequence(array $draftOrder, int $totalRounds): array
    {
        $sequence = [];

        for ($round = 1; $round <= $totalRounds; $round++) {
            if ($round % 2 === 1) {
                // Odd rounds: normal order
                $sequence = array_merge($sequence, $draftOrder);
            } else {
                // Even rounds: reversed order
                $sequence = array_merge($sequence, array_reverse($draftOrder));
            }
        }

        return $sequence;
    }

    /**
     * Weighted random shuffle: higher weight = more likely to be placed earlier.
     */
    private function weightedShuffle(array $weights): array
    {
        $remaining = $weights;
        $result = [];

        while (!empty($remaining)) {
            $totalWeight = array_sum(array_column($remaining, 'weight'));
            $rand = mt_rand(1, (int) ($totalWeight * 1000)) / 1000;

            $cumulative = 0;
            foreach ($remaining as $key => $data) {
                $cumulative += $data['weight'];
                if ($rand <= $cumulative) {
                    $result[] = $data['membership_id'];
                    unset($remaining[$key]);
                    break;
                }
            }
        }

        return $result;
    }
}
