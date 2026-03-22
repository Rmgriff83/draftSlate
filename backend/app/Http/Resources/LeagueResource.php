<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'state' => $this->state,
            'max_teams' => $this->max_teams,
            'buy_in' => $this->buy_in,
            'payout_structure' => $this->payout_structure,
            'roster_config' => $this->roster_config,
            'sports' => $this->sports,
            'aggregate_odds_floor' => $this->aggregate_odds_floor,
            'starter_count' => $this->getStarterSlotsCount(),
            'bench_count' => $this->getBenchSlotsCount(),
            'draft_day' => $this->draft_day,
            'draft_time' => $this->draft_time,
            'draft_timezone' => $this->draft_timezone,
            'pick_timer_seconds' => $this->pick_timer_seconds,
            'regular_season_weeks' => $this->regular_season_weeks,
            'playoff_format' => $this->playoff_format,
            'invite_code' => $this->when($this->isCommissioner($request), $this->invite_code),
            'current_week' => $this->current_week,
            'member_count' => $this->memberships_count ?? $this->memberships()->count(),
            'commissioner' => [
                'id' => $this->commissioner->id,
                'display_name' => $this->commissioner->display_name,
                'avatar_url' => $this->commissioner->avatar_url,
            ],
            'is_member' => $this->when(
                $request->user(),
                fn () => $this->isMember($request->user())
            ),
            'is_commissioner' => $this->when(
                $request->user(),
                fn () => $this->isCommissioner($request)
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function isCommissioner(Request $request): bool
    {
        return $request->user() && $this->commissioner_id === $request->user()->id;
    }
}
