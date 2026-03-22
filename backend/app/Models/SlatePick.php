<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlatePick extends Model
{
    protected $fillable = [
        'league_membership_id',
        'pick_selection_id',
        'slate_pool_id',
        'week',
        'position',
        'slot_number',
        'slot_type',
        'drafted_odds',
        'locked_odds',
        'odds_drift',
        'is_locked',
        'locked_at',
        'draft_round',
        'draft_pick_number',
    ];

    protected function casts(): array
    {
        return [
            'week' => 'integer',
            'slot_number' => 'integer',
            'drafted_odds' => 'integer',
            'locked_odds' => 'integer',
            'odds_drift' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'draft_round' => 'integer',
            'draft_pick_number' => 'integer',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(LeagueMembership::class, 'league_membership_id');
    }

    public function pickSelection(): BelongsTo
    {
        return $this->belongsTo(PickSelection::class);
    }

    public function slatePool(): BelongsTo
    {
        return $this->belongsTo(SlatePool::class);
    }
}
