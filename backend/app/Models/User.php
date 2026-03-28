<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, Billable;

    protected $fillable = [
        'display_name',
        'email',
        'password',
        'avatar_url',
        'google_id',
        'role',
        'max_leagues',
        'career_picks_graded',
        'career_picks_hit',
        'career_moneyline_hits',
        'career_spread_hits',
        'career_total_hits',
        'career_player_prop_hits',
        'career_gold_medals',
        'career_silver_medals',
        'career_bronze_medals',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'max_leagues' => 'integer',
            'career_picks_graded' => 'integer',
            'career_picks_hit' => 'integer',
            'career_moneyline_hits' => 'integer',
            'career_spread_hits' => 'integer',
            'career_total_hits' => 'integer',
            'career_player_prop_hits' => 'integer',
            'career_gold_medals' => 'integer',
            'career_silver_medals' => 'integer',
            'career_bronze_medals' => 'integer',
        ];
    }

    public function leagueMemberships(): HasMany
    {
        return $this->hasMany(\App\Models\LeagueMembership::class);
    }

    public function commissioning(): HasMany
    {
        return $this->hasMany(\App\Models\League::class, 'commissioner_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(\App\Models\Transaction::class);
    }

    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\League::class, 'league_memberships');
    }

    /**
     * Record a career hit for the given pick type.
     * Increments career_picks_graded, career_picks_hit, and the type-specific column.
     */
    public function recordCareerHit(string $pickType): void
    {
        $typeColumn = match ($pickType) {
            'moneyline' => 'career_moneyline_hits',
            'spread' => 'career_spread_hits',
            'total' => 'career_total_hits',
            'player_prop' => 'career_player_prop_hits',
            default => null,
        };

        $this->increment('career_picks_graded');
        $this->increment('career_picks_hit');

        if ($typeColumn) {
            $this->increment($typeColumn);
        }
    }

    /**
     * Record a graded pick that was not a hit (miss or push).
     * Only increments career_picks_graded.
     */
    public function recordCareerGraded(): void
    {
        $this->increment('career_picks_graded');
    }

    public function awardMedal(int $position): void
    {
        $column = match ($position) {
            1 => 'career_gold_medals',
            2 => 'career_silver_medals',
            3 => 'career_bronze_medals',
            default => null,
        };

        if ($column) {
            $this->increment($column);
        }
    }
}
