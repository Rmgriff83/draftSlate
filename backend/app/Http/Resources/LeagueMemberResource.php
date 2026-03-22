<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_name' => $this->team_name,
            'team_logo_url' => $this->team_logo_url,
            'wins' => $this->wins,
            'losses' => $this->losses,
            'ties' => $this->ties,
            'total_correct_picks' => $this->total_correct_picks,
            'total_opponent_correct_picks' => $this->total_opponent_correct_picks,
            'playoff_seed' => $this->playoff_seed,
            'final_position' => $this->final_position,
            'is_active' => $this->is_active,
            'user' => [
                'id' => $this->user->id,
                'display_name' => $this->user->display_name,
                'avatar_url' => $this->user->avatar_url,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
