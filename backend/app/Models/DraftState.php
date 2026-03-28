<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftState extends Model
{
    protected $fillable = [
        'league_id',
        'slate_pool_id',
        'week',
        'status',
        'draft_order',
        'draft_order_weights',
        'current_round',
        'current_pick_index',
        'current_drafter_id',
        'current_pick_started_at',
        'total_rounds',
        'started_at',
        'draft_starts_at',
        'completed_at',
        'auto_draft_members',
        'consecutive_auto_picks',
    ];

    protected function casts(): array
    {
        return [
            'week' => 'integer',
            'draft_order' => 'array',
            'draft_order_weights' => 'array',
            'current_round' => 'integer',
            'current_pick_index' => 'integer',
            'total_rounds' => 'integer',
            'current_pick_started_at' => 'datetime',
            'started_at' => 'datetime',
            'draft_starts_at' => 'datetime',
            'completed_at' => 'datetime',
            'auto_draft_members' => 'array',
            'consecutive_auto_picks' => 'array',
        ];
    }

    public function isInAutoDraft(int $membershipId): bool
    {
        return in_array($membershipId, $this->auto_draft_members ?? []);
    }

    public function getConsecutiveAutoPickCount(int $membershipId): int
    {
        return ($this->consecutive_auto_picks ?? [])[$membershipId] ?? 0;
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function slatePool(): BelongsTo
    {
        return $this->belongsTo(SlatePool::class);
    }

    public function currentDrafter(): BelongsTo
    {
        return $this->belongsTo(LeagueMembership::class, 'current_drafter_id');
    }
}
