<?php

namespace App\Services;

use App\Exceptions\InsufficientPicksException;
use App\Models\League;

class PoolCurationService
{
    public function __construct(
        private OddsMathService $oddsMath,
    ) {}

    /**
     * Curate a scarce, balanced pool from raw API picks.
     *
     * @param array $rawPicks Array of pick data arrays (pre-insertion)
     * @return array{picks: array, metadata: array}
     */
    public function curate(array $rawPicks, League $league, int $memberCount): array
    {
        $totalRounds = $league->getTotalRounds();
        $poolSize = ($memberCount * $totalRounds) + random_int(
            config('draftslate.curation.pool_buffer_min', 10),
            config('draftslate.curation.pool_buffer_max', 12),
        );

        // If raw picks are scarce, use everything available
        if (count($rawPicks) <= $poolSize) {
            return [
                'picks' => $rawPicks,
                'metadata' => [
                    'pool_size_target' => $poolSize,
                    'pool_size_actual' => count($rawPicks),
                    'raw_pick_count' => count($rawPicks),
                    'used_all_picks' => true,
                    'distribution' => [],
                    'shortfalls' => [],
                    'backfill_count' => 0,
                    'rescue_count' => 0,
                ],
            ];
        }

        // Classify and bucket all raw picks by {sport}:{pick_type}:{tier}
        $buckets = [];
        $allSports = [];
        $allTypes = [];

        foreach ($rawPicks as $index => $pick) {
            $tier = $this->classifyTier((int) ($pick['snapshot_odds'] ?? 0));
            $key = $this->buildBucketKey($pick['sport'] ?? 'unknown', $pick['pick_type'] ?? 'unknown', $tier);

            $buckets[$key][] = $index;
            $allSports[$pick['sport'] ?? 'unknown'] = true;
            $allTypes[$pick['pick_type'] ?? 'unknown'] = true;
        }

        $sports = array_keys($allSports);
        $types = array_keys($allTypes);
        $tiers = array_keys(config('draftslate.curation.tier_boundaries'));

        // Shuffle each bucket for random selection
        foreach ($buckets as &$bucket) {
            shuffle($bucket);
        }
        unset($bucket);

        // Calculate target distribution across all bucket combinations
        $distribution = $this->calculateDistribution($poolSize, $sports, $types, $tiers);

        // Select from each bucket up to target count
        $selected = [];
        $selectedSet = [];
        $shortfalls = [];

        foreach ($distribution as $key => $target) {
            $available = $buckets[$key] ?? [];
            $taken = min($target, count($available));

            for ($i = 0; $i < $taken; $i++) {
                $idx = $available[$i];
                $selected[] = $idx;
                $selectedSet[$idx] = true;
            }

            // Remove taken picks from bucket so backfill doesn't reuse them
            $buckets[$key] = array_slice($available, $taken);

            if ($taken < $target) {
                $shortfalls[$key] = $target - $taken;
            }
        }

        // Backfill shortfalls via cascade priority
        $backfillCount = $this->backfillShortfalls(
            $selected, $selectedSet, $buckets, $shortfalls, $sports, $types, $tiers, $poolSize,
        );

        // Floor validation — ensure enough likely picks for all teams
        $floorCheck = $this->validateFloorSafety($selected, $rawPicks, $league, $memberCount);
        $rescueCount = 0;

        if (!$floorCheck['valid']) {
            $rescueCount = $this->rescueFloor(
                $selected, $selectedSet, $buckets, $rawPicks, $floorCheck['deficit'],
            );

            $floorRecheck = $this->validateFloorSafety($selected, $rawPicks, $league, $memberCount);
            if (!$floorRecheck['valid']) {
                throw new InsufficientPicksException(
                    'Not enough favorable picks available to meet the aggregate odds floor for all teams.',
                    [
                        'pool_size_target' => $poolSize,
                        'likely_picks_needed' => $floorRecheck['likely_needed'],
                        'likely_picks_available' => $floorRecheck['likely_available'],
                        'deficit' => $floorRecheck['deficit'],
                    ],
                );
            }
        }

        // Build the curated picks array
        $curatedPicks = [];
        foreach ($selected as $idx) {
            $curatedPicks[] = $rawPicks[$idx];
        }

        return [
            'picks' => $curatedPicks,
            'metadata' => [
                'pool_size_target' => $poolSize,
                'pool_size_actual' => count($curatedPicks),
                'raw_pick_count' => count($rawPicks),
                'used_all_picks' => false,
                'distribution' => $distribution,
                'shortfalls' => $shortfalls,
                'backfill_count' => $backfillCount,
                'rescue_count' => $rescueCount,
                'sports' => $sports,
                'tier_counts' => $this->countByTier($curatedPicks),
            ],
        ];
    }

    /**
     * Classify American odds into a tier name.
     */
    public function classifyTier(int $odds): string
    {
        $boundaries = config('draftslate.curation.tier_boundaries');

        if ($odds <= ($boundaries['likely']['max_odds'] ?? -150)) {
            return 'likely';
        }

        if ($odds <= ($boundaries['relatively_unlikely']['max_odds'] ?? 100)) {
            return 'relatively_unlikely';
        }

        if ($odds <= ($boundaries['unlikely']['max_odds'] ?? 250)) {
            return 'unlikely';
        }

        return 'extremely_unlikely';
    }

    /**
     * Build a bucket key from sport, pick type, and tier.
     */
    public function buildBucketKey(string $sport, string $pickType, string $tier): string
    {
        return "{$sport}:{$pickType}:{$tier}";
    }

    /**
     * Distribute pool size equally across all sport × type × tier buckets.
     * Remainders are spread round-robin.
     */
    public function calculateDistribution(int $poolSize, array $sports, array $types, array $tiers): array
    {
        $distribution = [];
        $totalBuckets = count($sports) * count($types) * count($tiers);

        if ($totalBuckets === 0) {
            return [];
        }

        $basePerBucket = intdiv($poolSize, $totalBuckets);
        $remainder = $poolSize % $totalBuckets;

        $allKeys = [];
        foreach ($sports as $sport) {
            foreach ($types as $type) {
                foreach ($tiers as $tier) {
                    $key = $this->buildBucketKey($sport, $type, $tier);
                    $allKeys[] = $key;
                    $distribution[$key] = $basePerBucket;
                }
            }
        }

        // Spread remainder round-robin across buckets
        for ($i = 0; $i < $remainder; $i++) {
            $distribution[$allKeys[$i]]++;
        }

        return $distribution;
    }

    /**
     * Backfill shortfalls using cascade priority:
     * 1. Same sport + same type + adjacent tier
     * 2. Same sport + different type + same tier
     * 3. Different sport + same type + same tier
     * 4. Any remaining unselected pick (prefer likely tier)
     */
    private function backfillShortfalls(
        array &$selected,
        array &$selectedSet,
        array &$buckets,
        array $shortfalls,
        array $sports,
        array $types,
        array $tiers,
        int $poolSize,
    ): int {
        $backfilled = 0;

        foreach ($shortfalls as $key => $deficit) {
            [$sport, $type, $tier] = explode(':', $key);
            $needed = $deficit;

            // Priority 1: same sport + same type + adjacent tier
            $tierIndex = array_search($tier, $tiers);
            $adjacentTiers = [];
            if ($tierIndex !== false) {
                if ($tierIndex > 0) {
                    $adjacentTiers[] = $tiers[$tierIndex - 1];
                }
                if ($tierIndex < count($tiers) - 1) {
                    $adjacentTiers[] = $tiers[$tierIndex + 1];
                }
            }
            foreach ($adjacentTiers as $adjTier) {
                if ($needed <= 0) {
                    break;
                }
                $needed -= $this->takeFromBucket($buckets, $selected, $selectedSet, "{$sport}:{$type}:{$adjTier}", $needed);
            }

            // Priority 2: same sport + different type + same tier
            if ($needed > 0) {
                foreach ($types as $altType) {
                    if ($altType === $type || $needed <= 0) {
                        continue;
                    }
                    $needed -= $this->takeFromBucket($buckets, $selected, $selectedSet, "{$sport}:{$altType}:{$tier}", $needed);
                }
            }

            // Priority 3: different sport + same type + same tier
            if ($needed > 0) {
                foreach ($sports as $altSport) {
                    if ($altSport === $sport || $needed <= 0) {
                        continue;
                    }
                    $needed -= $this->takeFromBucket($buckets, $selected, $selectedSet, "{$altSport}:{$type}:{$tier}", $needed);
                }
            }

            // Priority 4: any remaining (prefer likely tier for floor safety)
            if ($needed > 0) {
                foreach ($sports as $s) {
                    foreach ($types as $t) {
                        if ($needed <= 0) {
                            break 2;
                        }
                        $needed -= $this->takeFromBucket($buckets, $selected, $selectedSet, "{$s}:{$t}:likely", $needed);
                    }
                }
                // Then truly any remaining bucket
                foreach ($buckets as $bKey => $bucket) {
                    if ($needed <= 0) {
                        break;
                    }
                    $needed -= $this->takeFromBucket($buckets, $selected, $selectedSet, $bKey, $needed);
                }
            }

            $backfilled += ($deficit - $needed);
        }

        // If still short of poolSize after all shortfalls, fill from any remaining
        $stillNeeded = $poolSize - count($selected);
        if ($stillNeeded > 0) {
            foreach ($buckets as $bKey => $bucket) {
                if ($stillNeeded <= 0) {
                    break;
                }
                $took = $this->takeFromBucket($buckets, $selected, $selectedSet, $bKey, $stillNeeded);
                $stillNeeded -= $took;
                $backfilled += $took;
            }
        }

        return $backfilled;
    }

    /**
     * Take up to $max picks from a specific bucket.
     */
    private function takeFromBucket(
        array &$buckets,
        array &$selected,
        array &$selectedSet,
        string $key,
        int $max,
    ): int {
        if (!isset($buckets[$key]) || empty($buckets[$key]) || $max <= 0) {
            return 0;
        }

        $took = 0;
        $remaining = [];

        foreach ($buckets[$key] as $idx) {
            if ($took >= $max) {
                $remaining[] = $idx;
                continue;
            }
            if (isset($selectedSet[$idx])) {
                continue;
            }
            $selected[] = $idx;
            $selectedSet[$idx] = true;
            $took++;
        }

        $buckets[$key] = $remaining;

        return $took;
    }

    /**
     * Validate that enough "likely" picks exist for all teams to meet the aggregate floor.
     *
     * Heuristic: each team needs at least 30% of their roster as likely picks
     * to have a realistic chance of meeting the aggregate odds floor.
     */
    private function validateFloorSafety(
        array $selected,
        array $rawPicks,
        League $league,
        int $memberCount,
    ): array {
        $totalRounds = $league->getTotalRounds();
        $minLikelyPerTeam = (int) ceil($totalRounds * 0.3);
        $likelyNeeded = $minLikelyPerTeam * $memberCount;

        $likelyCount = 0;
        foreach ($selected as $idx) {
            $odds = (int) ($rawPicks[$idx]['snapshot_odds'] ?? 0);
            if ($this->classifyTier($odds) === 'likely') {
                $likelyCount++;
            }
        }

        return [
            'valid' => $likelyCount >= $likelyNeeded,
            'deficit' => max(0, $likelyNeeded - $likelyCount),
            'likely_needed' => $likelyNeeded,
            'likely_available' => $likelyCount,
        ];
    }

    /**
     * Swap risky picks in the selected pool for unused likely picks from buckets.
     * Targets extremely_unlikely first, then unlikely, then relatively_unlikely.
     */
    private function rescueFloor(
        array &$selected,
        array &$selectedSet,
        array &$buckets,
        array $rawPicks,
        int $deficit,
    ): int {
        // Collect unused likely picks from all buckets
        $unusedLikely = [];
        foreach ($buckets as $key => $bucket) {
            if (!str_ends_with($key, ':likely')) {
                continue;
            }
            foreach ($bucket as $bIdx => $idx) {
                if (!isset($selectedSet[$idx])) {
                    $unusedLikely[] = ['bucket_key' => $key, 'bucket_index' => $bIdx, 'pick_index' => $idx];
                }
            }
        }

        if (empty($unusedLikely)) {
            return 0;
        }

        // Find risky picks in selected to swap out (riskiest first)
        $riskyTiers = ['extremely_unlikely', 'unlikely', 'relatively_unlikely'];
        $swappable = [];
        foreach ($selected as $selPos => $idx) {
            $odds = (int) ($rawPicks[$idx]['snapshot_odds'] ?? 0);
            $tier = $this->classifyTier($odds);
            if (in_array($tier, $riskyTiers, true)) {
                $priority = array_search($tier, $riskyTiers);
                $swappable[] = ['position' => $selPos, 'pick_index' => $idx, 'priority' => $priority];
            }
        }

        // Sort: extremely_unlikely swapped out first
        usort($swappable, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        $rescued = 0;
        $likelyIdx = 0;

        foreach ($swappable as $swap) {
            if ($rescued >= $deficit || $likelyIdx >= count($unusedLikely)) {
                break;
            }

            $likelyPick = $unusedLikely[$likelyIdx];

            // Remove risky pick from selected
            unset($selectedSet[$swap['pick_index']]);

            // Replace in-place with likely pick
            $selected[$swap['position']] = $likelyPick['pick_index'];
            $selectedSet[$likelyPick['pick_index']] = true;

            $rescued++;
            $likelyIdx++;
        }

        return $rescued;
    }

    /**
     * Count picks by tier for metadata reporting.
     */
    private function countByTier(array $picks): array
    {
        $counts = [
            'likely' => 0,
            'relatively_unlikely' => 0,
            'unlikely' => 0,
            'extremely_unlikely' => 0,
        ];

        foreach ($picks as $pick) {
            $tier = $this->classifyTier((int) ($pick['snapshot_odds'] ?? 0));
            $counts[$tier]++;
        }

        return $counts;
    }
}
