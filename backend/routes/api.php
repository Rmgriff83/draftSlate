<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\HeadshotController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\TeamLogoController;
use App\Http\Controllers\MatchupController;
use App\Http\Controllers\PickController;
use App\Http\Controllers\SlateController;
use App\Http\Controllers\StandingsController;
use App\Http\Controllers\PlayoffController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['status' => 'ok']));

Route::prefix('v1')->group(function () {
    // Auth — public
    Route::prefix('auth')->group(function () {
        Route::post('/register', RegisterController::class);
        Route::post('/login', [LoginController::class, 'login']);

        // Google OAuth
        Route::get('/google/redirect', [SocialAuthController::class, 'redirectToGoogle']);
        Route::get('/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);
    });

    // Auth — authenticated
    Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
        Route::get('/user', [LoginController::class, 'user']);
        Route::post('/logout', [LoginController::class, 'logout']);
    });

    // Leagues — authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/leagues', [LeagueController::class, 'index']);
        Route::post('/leagues', [LeagueController::class, 'store']);
        Route::get('/leagues/join/{inviteCode}', [LeagueController::class, 'showByInviteCode']);
        Route::get('/leagues/{league}', [LeagueController::class, 'show']);
        Route::put('/leagues/{league}', [LeagueController::class, 'update']);
        Route::delete('/leagues/{league}', [LeagueController::class, 'destroy']);
        Route::post('/leagues/{league}/join', [LeagueController::class, 'join']);
        Route::post('/leagues/{league}/leave', [LeagueController::class, 'leave']);

        // Slate management
        Route::get('/leagues/{league}/slate/{week}', [SlateController::class, 'show']);
        Route::get('/leagues/{league}/slate/{week}/summary', [SlateController::class, 'summary']);
        Route::post('/leagues/{league}/slate/swap', [SlateController::class, 'swap']);
        Route::post('/leagues/{league}/slate/refresh-odds', [SlateController::class, 'refreshOdds']);

        // Matchups & Standings
        Route::get('/leagues/{league}/matchups/{week}', [MatchupController::class, 'index']);
        Route::get('/leagues/{league}/matchups/{week}/mine', [MatchupController::class, 'mine']);
        Route::get('/leagues/{league}/standings', [StandingsController::class, 'index']);

        // Headshots & Logos
        Route::get('/headshots/{league}', [HeadshotController::class, 'show']);
        Route::get('/logos/{league}', [TeamLogoController::class, 'show']);

        // Pick detail
        Route::get('/picks/{pickSelection}/study', [PickController::class, 'study']);
        Route::get('/picks/{pickSelection}/odds-history', [PickController::class, 'oddsHistory']);

        // Users
        Route::get('/users/{user}/career-stats', [UserController::class, 'careerStats']);

        // Draft
        Route::get('/leagues/{league}/draft', [DraftController::class, 'show']);
        Route::get('/leagues/{league}/draft/pool', [DraftController::class, 'getPool']);
        Route::post('/leagues/{league}/draft/pick', [DraftController::class, 'submitPick']);
        Route::get('/leagues/{league}/draft/order', [DraftController::class, 'getOrder']);

        Route::post('/leagues/{league}/draft/autodraft/disable', [DraftController::class, 'disableAutoDraft']);

        // Playoffs
        Route::get('/leagues/{league}/playoffs/bracket', [PlayoffController::class, 'bracket']);
        Route::get('/leagues/{league}/playoffs/payouts', [PlayoffController::class, 'payouts']);
    });
});
