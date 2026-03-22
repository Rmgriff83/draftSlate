<?php

namespace App\Services;

class OddsMathService
{
    /**
     * Convert American odds to implied probability (0.0 to 1.0).
     *
     * For negative odds (favorites): prob = |odds| / (|odds| + 100)
     * For positive odds (underdogs): prob = 100 / (odds + 100)
     */
    public function americanToImpliedProbability(int $americanOdds): float
    {
        if ($americanOdds < 0) {
            return abs($americanOdds) / (abs($americanOdds) + 100);
        }

        return 100 / ($americanOdds + 100);
    }

    /**
     * Convert implied probability back to American odds.
     */
    public function impliedProbabilityToAmerican(float $prob): int
    {
        if ($prob <= 0 || $prob >= 1) {
            return -100;
        }

        if ($prob >= 0.5) {
            return (int) round(-100 * $prob / (1 - $prob));
        }

        return (int) round(100 * (1 - $prob) / $prob);
    }

    /**
     * Calculate average implied probability across a set of American odds.
     */
    public function calculateAggregateImpliedProbability(array $oddsList): float
    {
        if (empty($oddsList)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($oddsList as $odds) {
            $total += $this->americanToImpliedProbability((int) $odds);
        }

        return $total / count($oddsList);
    }

    /**
     * Check if adding a new pick's odds to existing picks would keep the
     * aggregate average implied probability under the floor.
     *
     * The floor is in American odds (e.g. -250). The aggregate average
     * implied probability must stay <= the floor's implied probability
     * (i.e., the roster must remain risky enough on average).
     */
    public function aggregateMeetsFloor(array $currentPickOdds, int $newPickOdds, int $floor): bool
    {
        $allOdds = array_merge($currentPickOdds, [$newPickOdds]);
        $avgProb = $this->calculateAggregateImpliedProbability($allOdds);
        $floorProb = $this->americanToImpliedProbability($floor);

        return $avgProb <= $floorProb;
    }

    /**
     * Check if odds meet/exceed a floor (e.g., -250).
     *
     * "Meet" means the pick is at or above the floor — i.e., NOT safer than the floor.
     * -200 meets -250 (riskier pick, lower implied probability). ✓
     * -300 does NOT meet -250 (too safe, higher implied probability). ✗
     * +150 meets -250 (much riskier). ✓
     */
    public function meetsOddsFloor(int $pickOdds, int $floor): bool
    {
        $pickProb = $this->americanToImpliedProbability($pickOdds);
        $floorProb = $this->americanToImpliedProbability($floor);

        // Pick must have equal or lower implied probability (riskier)
        return $pickProb <= $floorProb;
    }

    /**
     * Check if odds fall within a band [min, max].
     *
     * Both min and max are in American odds. The pick's implied probability
     * must fall between the two bounds.
     */
    public function isWithinBand(int $pickOdds, int $bandMin, int $bandMax): bool
    {
        $pickProb = $this->americanToImpliedProbability($pickOdds);
        $minProb = $this->americanToImpliedProbability($bandMin);
        $maxProb = $this->americanToImpliedProbability($bandMax);

        // Ensure min/max probs are ordered correctly
        $lowerProb = min($minProb, $maxProb);
        $upperProb = max($minProb, $maxProb);

        return $pickProb >= $lowerProb && $pickProb <= $upperProb;
    }

    /**
     * Calculate odds drift between two American odds values.
     *
     * Returns the delta in implied probability basis points (positive = favorable drift,
     * meaning the pick became riskier / higher payout since draft).
     */
    public function calculateOddsDrift(int $draftedOdds, int $lockedOdds): int
    {
        $draftedProb = $this->americanToImpliedProbability($draftedOdds);
        $lockedProb = $this->americanToImpliedProbability($lockedOdds);

        // Positive drift = locked probability is lower = odds moved in drafter's favor
        return (int) round(($draftedProb - $lockedProb) * 10000);
    }
}
