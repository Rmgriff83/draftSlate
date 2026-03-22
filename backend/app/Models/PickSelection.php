<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickSelection extends Model
{
    protected $fillable = [
        'slate_pool_id',
        'external_id',
        'description',
        'pick_type',
        'category',
        'player_name',
        'home_team',
        'away_team',
        'game_display',
        'game_time',
        'sport',
        'snapshot_odds',
        'current_odds',
        'odds_updated_at',
        'outcome',
        'result_data',
        'is_drafted',
    ];

    protected function casts(): array
    {
        return [
            'game_time' => 'datetime',
            'odds_updated_at' => 'datetime',
            'snapshot_odds' => 'integer',
            'current_odds' => 'integer',
            'result_data' => 'array',
            'is_drafted' => 'boolean',
        ];
    }

    public function slatePool(): BelongsTo
    {
        return $this->belongsTo(SlatePool::class);
    }

    public function slatePicks(): HasMany
    {
        return $this->hasMany(SlatePick::class);
    }
}
