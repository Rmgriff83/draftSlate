<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Season extends Model
{
    protected $fillable = [
        'league_id',
        'year',
        'start_week',
        'end_week',
        'playoff_start_week',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'start_week' => 'integer',
            'end_week' => 'integer',
            'playoff_start_week' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
