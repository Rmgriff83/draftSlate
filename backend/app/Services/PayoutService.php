<?php

namespace App\Services;

use App\Models\League;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class PayoutService
{
    public function calculatePayouts(League $league): array
    {
        $totalPot = (float) $league->buy_in * $league->memberships()->where('is_active', true)->count();
        $commission = round($totalPot * 0.10, 2);
        $distributable = round($totalPot - $commission, 2);

        $structure = $league->payout_structure;
        if (!$structure) {
            return [
                'total_pot' => $totalPot,
                'commission' => $commission,
                'distributable' => $distributable,
                'payouts' => [],
            ];
        }

        $payouts = [];

        // Standard payout positions: first, second, third, weekly
        $positionMap = [
            'first' => 1,
            'second' => 2,
            'third' => 3,
        ];

        foreach ($positionMap as $key => $position) {
            if (isset($structure[$key]) && $structure[$key] > 0) {
                $amount = round($distributable * ($structure[$key] / 100), 2);
                $payouts[] = [
                    'position' => $position,
                    'percentage' => $structure[$key],
                    'amount' => $amount,
                ];
            }
        }

        // Weekly prizes are not position-based, handled separately if needed
        if (isset($structure['weekly']) && $structure['weekly'] > 0) {
            $payouts[] = [
                'position' => null,
                'percentage' => $structure['weekly'],
                'amount' => round($distributable * ($structure['weekly'] / 100), 2),
                'type' => 'weekly',
            ];
        }

        return [
            'total_pot' => $totalPot,
            'commission' => $commission,
            'distributable' => $distributable,
            'payouts' => $payouts,
        ];
    }

    public function distributePayouts(League $league): array
    {
        $calculation = $this->calculatePayouts($league);
        $transactions = [];

        // Commission transaction
        Transaction::create([
            'user_id' => $league->commissioner_id,
            'league_id' => $league->id,
            'type' => 'commission',
            'amount' => $calculation['commission'],
            'status' => 'completed',
            'notes' => "Platform commission (10%) for {$league->name}",
        ]);

        // Payout transactions
        $memberships = $league->memberships()
            ->where('is_active', true)
            ->whereNotNull('final_position')
            ->with('user')
            ->get()
            ->keyBy('final_position');

        foreach ($calculation['payouts'] as $payout) {
            if ($payout['position'] === null) {
                continue; // Skip weekly prizes for now
            }

            $member = $memberships->get($payout['position']);
            if (!$member) {
                continue;
            }

            $tx = Transaction::create([
                'user_id' => $member->user_id,
                'league_id' => $league->id,
                'type' => 'payout',
                'amount' => $payout['amount'],
                'status' => 'completed',
                'notes' => "#{$payout['position']} place payout for {$league->name}",
            ]);

            $transactions[] = [
                'position' => $payout['position'],
                'user_id' => $member->user_id,
                'amount' => $payout['amount'],
                'transaction_id' => $tx->id,
            ];
        }

        Log::info("PayoutService: Distributed payouts for league {$league->id}", [
            'total_pot' => $calculation['total_pot'],
            'commission' => $calculation['commission'],
            'payout_count' => count($transactions),
        ]);

        return $transactions;
    }
}
