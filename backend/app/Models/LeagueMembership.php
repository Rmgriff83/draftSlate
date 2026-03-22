<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueMembership extends Model
{
    protected $fillable = [
        'user_id',
        'league_id',
        'team_name',
        'team_logo_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'wins' => 'integer',
            'losses' => 'integer',
            'ties' => 'integer',
            'total_correct_picks' => 'integer',
            'total_opponent_correct_picks' => 'integer',
            'playoff_seed' => 'integer',
            'final_position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function slatePicks(): HasMany
    {
        return $this->hasMany(\App\Models\SlatePick::class);
    }

    public function homeMatchups(): HasMany
    {
        return $this->hasMany(\App\Models\Matchup::class, 'home_team_id');
    }

    public function awayMatchups(): HasMany
    {
        return $this->hasMany(\App\Models\Matchup::class, 'away_team_id');
    }
}
