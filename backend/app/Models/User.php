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
}
