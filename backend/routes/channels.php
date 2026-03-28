<?php

use Illuminate\Support\Facades\Broadcast;

// Presence channel per league draft — tracks who's in the draft room
Broadcast::channel('draft.{leagueId}', function ($user, int $leagueId) {
    $membership = \App\Models\LeagueMembership::where('user_id', $user->id)
        ->where('league_id', $leagueId)
        ->first();

    if (!$membership) {
        return false;
    }

    return [
        'id' => $membership->id,
        'user_id' => $user->id,
        'team_name' => $membership->team_name,
        'user_name' => $user->display_name,
        'avatar_url' => $user->avatar_url,
    ];
});

// Private channel per league — general league events
Broadcast::channel('league.{leagueId}', function ($user, int $leagueId) {
    return $user->leagues()->where('leagues.id', $leagueId)->exists();
});

// Private channel per user — personal notifications
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});
