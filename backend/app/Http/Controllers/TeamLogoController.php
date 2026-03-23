<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class TeamLogoController extends Controller
{
    private const VALID_LEAGUES = ['nba', 'mlb', 'nhl', 'nfl'];

    public function show(string $league): JsonResponse
    {
        if (!in_array($league, self::VALID_LEAGUES, true)) {
            abort(404, 'Unsupported league.');
        }

        $disk = Storage::disk(config('draftslate.headshots.disk'));
        $path = "logos/{$league}.json";

        if (!$disk->exists($path)) {
            abort(404, 'Logo data not yet generated for this league.');
        }

        $data = json_decode($disk->get($path), true);

        return response()->json($data)
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
