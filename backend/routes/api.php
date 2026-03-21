<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
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
});
