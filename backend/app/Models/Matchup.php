<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matchup extends Model
{
    protected $table = 'weekly_matchups';

    protected $fillable = [
        'league_id',
        'week',
        'home_team_id',
        'away_team_id',
        'home_score',
        'away_score',
        'winner_id',
        'is_tie',
        'is_playoff',
        'playoff_round',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'week' => 'integer',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'is_tie' => 'boolean',
            'is_playoff' => 'boolean',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueMembership::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(LeagueMembership::class, 'away_team_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(LeagueMembership::class, 'winner_id');
    }
}
