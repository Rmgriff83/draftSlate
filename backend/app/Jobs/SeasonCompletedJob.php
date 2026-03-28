<?php

namespace App\Jobs;

use App\Events\SeasonCompleted;
use App\Models\FeedItem;
use App\Models\League;
use App\Services\PayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SeasonCompletedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $leagueId,
    ) {
        $this->onQueue('default');
    }

    public function handle(PayoutService $payoutService): void
    {
        $league = League::find($this->leagueId);

        if (!$league) {
            return;
        }

        // 1. Distribute payouts
        $payoutResults = $payoutService->distributePayouts($league);
        $payoutByPosition = collect($payoutResults)->keyBy('position');

        // 2. Award medals
        $memberships = $league->memberships()
            ->where('is_active', true)
            ->whereNotNull('final_position')
            ->with('user')
            ->get();

        foreach ($memberships as $member) {
            if ($member->final_position <= 3 && $member->user) {
                $member->user->awardMedal($member->final_position);
            }
        }

        // 3. Create personalized feed items
        $champion = $memberships->firstWhere('final_position', 1);

        foreach ($memberships as $member) {
            $position = $member->final_position;
            $payout = $payoutByPosition->get($position);

            if ($payout) {
                // Payout winner
                $medalLabel = $this->medalLabel($position);
                $message = "Congratulations! You finished #{$position} in {$league->name} and won \${$payout['amount']}!";
                $metadata = [
                    'position' => $position,
                    'payout_amount' => $payout['amount'],
                    'medal' => $medalLabel,
                ];
            } elseif ($position <= 3) {
                // Medal winner without payout
                $medalLabel = $this->medalLabel($position);
                $message = "You earned a {$medalLabel} medal in {$league->name}! #{$position} finish.";
                $metadata = [
                    'position' => $position,
                    'medal' => $medalLabel,
                ];
            } else {
                // Everyone else
                $message = "The {$league->name} season is complete. You finished #{$position}. Better luck next time!";
                $metadata = [
                    'position' => $position,
                ];
            }

            FeedItem::create([
                'league_id' => $league->id,
                'user_id' => $member->user_id,
                'event_type' => 'season_completed',
                'message' => $message,
                'metadata' => $metadata,
            ]);
        }

        // 4. League-wide champion announcement
        if ($champion) {
            FeedItem::create([
                'league_id' => $league->id,
                'user_id' => null,
                'event_type' => 'season_completed',
                'message' => "{$champion->team_name} wins the {$league->name} championship!",
                'metadata' => [
                    'champion_team_name' => $champion->team_name,
                    'champion_user_id' => $champion->user_id,
                ],
            ]);
        }

        // 5. Broadcast
        event(new SeasonCompleted($league->id));

        Log::info("SeasonCompletedJob: Completed for league {$this->leagueId}");
    }

    private function medalLabel(int $position): ?string
    {
        return match ($position) {
            1 => 'gold',
            2 => 'silver',
            3 => 'bronze',
            default => null,
        };
    }
}
