<?php

return [

    'sport' => env('DRAFTSLATE_SPORT', 'nfl'),

    'leagues' => [
        'min_teams' => 4,
        'max_teams' => 14,
        'min_buy_in' => 5.00,
        'default_starter_slots' => 5,
        'default_bench_slots' => 3,
        'default_pick_timer' => 60,
        'default_regular_season_weeks' => 14,
    ],

    'draft' => [
        'auto_pick_timeout_buffer' => 5,
    ],

    'odds' => [
        'default_global_floor' => -250,
        'refresh_interval_hours' => 3,
    ],

    'platform_commission_rate' => 0.10,

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
