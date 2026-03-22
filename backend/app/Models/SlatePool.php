<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SlatePool extends Model
{
    protected $fillable = [
        'league_id',
        'week',
        'snapshot_at',
        'status',
        'api_metadata',
    ];

    protected function casts(): array
    {
        return [
            'week' => 'integer',
            'snapshot_at' => 'datetime',
            'api_metadata' => 'array',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function pickSelections(): HasMany
    {
        return $this->hasMany(PickSelection::class);
    }

    public function slatePicks(): HasMany
    {
        return $this->hasMany(SlatePick::class);
    }

    public function draftState(): HasOne
    {
        return $this->hasOne(DraftState::class);
    }
}
