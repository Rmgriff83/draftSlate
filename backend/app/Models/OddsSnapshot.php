<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OddsSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pick_selection_id',
        'odds',
        'line',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'odds' => 'integer',
            'line' => 'decimal:1',
            'captured_at' => 'datetime',
        ];
    }

    public function pickSelection(): BelongsTo
    {
        return $this->belongsTo(PickSelection::class);
    }
}
