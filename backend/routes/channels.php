<?php

use Illuminate\Support\Facades\Broadcast;

// Private channel per league draft — all league members can listen
Broadcast::channel('draft.{leagueId}', function ($user, int $leagueId) {
    return $user->leagues()->where('leagues.id', $leagueId)->exists();
});

// Private channel per league — general league events
Broadcast::channel('league.{leagueId}', function ($user, int $leagueId) {
    return $user->leagues()->where('leagues.id', $leagueId)->exists();
});

// Private channel per user — personal notifications
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});
