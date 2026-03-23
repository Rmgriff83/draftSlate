<?php

return [

    'leagues' => [
        'min_teams' => 4,
        'max_teams' => 14,
        'min_buy_in' => 5.00,
        'default_roster_config' => ['moneyline' => 1, 'spread' => 1, 'total' => 1, 'player_prop' => 2],
        'default_aggregate_odds_floor' => -250,
        'default_bench_slots' => 2,
        'supported_sports' => [
            'basketball_nba' => 'NBA',
            'americanfootball_nfl' => 'NFL',
            'baseball_mlb' => 'MLB',
            'icehockey_nhl' => 'NHL',
        ],
        'default_pick_timer' => 60,
        'default_matchup_duration_days' => 7,
        'default_total_matchups' => 14,
        'default_min_hours_before_game' => 1,
    ],

    'draft' => [
        'auto_pick_timeout_buffer' => 5,
    ],

    'odds' => [
        'default_aggregate_floor' => -250,
        'refresh_interval_hours' => 3,
    ],

    'odds_api' => [
        'key' => env('ODDS_API_KEY'),
        'regions' => 'us',
        'odds_format' => 'american',
        'bookmaker' => env('ODDS_API_PRIMARY_BOOK', 'fanduel'),
        'player_prop_markets_by_sport' => [
            'americanfootball_nfl' => ['player_pass_yds', 'player_pass_tds', 'player_rush_yds', 'player_receptions', 'player_reception_yds', 'player_anytime_td'],
            'basketball_nba' => ['player_points', 'player_rebounds', 'player_assists', 'player_threes'],
            'baseball_mlb' => ['pitcher_strikeouts', 'batter_hits', 'batter_total_bases', 'batter_home_runs'],
            'icehockey_nhl' => ['player_points', 'player_shots_on_goal', 'player_assists'],
        ],
        'game_markets' => ['h2h', 'spreads', 'totals'],
        'refresh_interval_hours' => env('ODDS_API_REFRESH_INTERVAL_HOURS', 3),
        'pool_build_minutes_before_draft' => 30,
        'min_hours_before_game' => env('ODDS_API_MIN_HOURS_BEFORE_GAME', 1),
        'cache_ttl_seconds' => env('ODDS_API_CACHE_TTL_SECONDS', 180),
    ],

    'curation' => [
        'pool_buffer_min' => 10,
        'pool_buffer_max' => 12,
        'tier_boundaries' => [
            'likely'              => ['max_odds' => -150],
            'relatively_unlikely' => ['min_odds' => -149, 'max_odds' => 100],
            'unlikely'            => ['min_odds' => 101,  'max_odds' => 250],
            'extremely_unlikely'  => ['min_odds' => 251],
        ],
        'worst_case_risky_odds' => 200,
    ],

    'game_logs' => [
        'balldontlie_base_url' => 'https://api.balldontlie.io/v1',
        'balldontlie_api_key' => env('BALLDONTLIE_API_KEY', ''),
    ],

    'headshots' => [
        'disk' => env('HEADSHOTS_DISK', 'public'),
    ],

    'platform_commission_rate' => 0.10,

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
