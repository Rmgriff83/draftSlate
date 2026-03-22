<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class League extends Model
{
    protected $attributes = [
        'roster_config' => '{"moneyline":1,"spread":1,"total":1,"player_prop":2}',
        'sports' => '["basketball_nba"]',
        'aggregate_odds_floor' => -250,
    ];

    protected $fillable = [
        'commissioner_id',
        'name',
        'type',
        'state',
        'max_teams',
        'buy_in',
        'payout_structure',
        'roster_config',
        'sports',
        'aggregate_odds_floor',
        'draft_day',
        'draft_time',
        'draft_timezone',
        'pick_timer_seconds',
        'regular_season_weeks',
        'playoff_format',
        'invite_code',
        'current_week',
    ];

    protected function casts(): array
    {
        return [
            'payout_structure' => 'array',
            'roster_config' => 'array',
            'sports' => 'array',
            'aggregate_odds_floor' => 'integer',
            'buy_in' => 'decimal:2',
            'max_teams' => 'integer',
            'draft_day' => 'integer',
            'pick_timer_seconds' => 'integer',
            'regular_season_weeks' => 'integer',
            'current_week' => 'integer',
        ];
    }

    public function getStarterSlotsCount(): int
    {
        return array_sum($this->roster_config ?? []);
    }

    public function getBenchSlotsCount(): int
    {
        return $this->getStarterSlotsCount();
    }

    public function getTotalRounds(): int
    {
        return $this->getStarterSlotsCount() + $this->getBenchSlotsCount();
    }

    public function getRosterSlotsByType(): array
    {
        return $this->roster_config ?? [];
    }

    public function getUnfilledStarterSlots(Collection $existingPicks): array
    {
        $config = $this->roster_config ?? [];
        $unfilled = [];

        foreach ($config as $type => $count) {
            $filled = $existingPicks->where('position', 'starter')
                ->where('slot_type', $type)->count();
            $remaining = $count - $filled;
            if ($remaining > 0) {
                $unfilled[$type] = $remaining;
            }
        }

        return $unfilled;
    }

    public function commissioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissioner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_memberships');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(\App\Models\Season::class);
    }

    public function slatePools(): HasMany
    {
        return $this->hasMany(\App\Models\SlatePool::class);
    }

    public function matchups(): HasMany
    {
        return $this->hasMany(\App\Models\Matchup::class);
    }

    public function draftStates(): HasMany
    {
        return $this->hasMany(\App\Models\DraftState::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function feedItems(): HasMany
    {
        return $this->hasMany(\App\Models\FeedItem::class);
    }

    public function currentSeason(): HasOne
    {
        return $this->hasOne(\App\Models\Season::class)->latestOfMany();
    }

    public function activeDraft(): HasOne
    {
        return $this->hasOne(\App\Models\DraftState::class)->where('status', 'active');
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('type', 'public')->where('state', 'pending');
    }

    public function scopeJoinable(Builder $query): Builder
    {
        return $query->where('state', 'pending')
            ->whereColumn(
                'id',
                'id'
            )
            ->withCount('memberships')
            ->having('memberships_count', '<', \Illuminate\Support\Facades\DB::raw('max_teams'));
    }

    public static function generateInviteCode(): string
    {
        do {
            $code = Str::random(12);
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }

    public function isFull(): bool
    {
        return $this->memberships()->count() >= $this->max_teams;
    }

    public function isMember(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->exists();
    }
}
