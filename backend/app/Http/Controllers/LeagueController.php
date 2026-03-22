<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeagueMemberResource;
use App\Http\Resources\LeagueResource;
use App\Models\League;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LeagueController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // Return user's own leagues when my_leagues flag is set
        if ($request->boolean('my_leagues')) {
            $leagues = $request->user()
                ->leagues()
                ->withCount('memberships')
                ->with('commissioner')
                ->latest()
                ->get();

            return LeagueResource::collection($leagues);
        }

        $query = League::public()
            ->withCount('memberships')
            ->with('commissioner');

        if ($request->filled('sport')) {
            $query->whereJsonContains('sports', $request->input('sport'));
        }

        if ($request->filled('state')) {
            $query->where('state', $request->input('state'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('buy_in_min')) {
            $query->where('buy_in', '>=', $request->input('buy_in_min'));
        }

        if ($request->filled('buy_in_max')) {
            $query->where('buy_in', '<=', $request->input('buy_in_max'));
        }

        if ($request->filled('has_spots')) {
            $query->whereRaw('(SELECT COUNT(*) FROM league_memberships WHERE league_memberships.league_id = leagues.id) < leagues.max_teams');
        }

        return LeagueResource::collection(
            $query->latest()->paginate(15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['public', 'private'])],
            'max_teams' => ['required', 'integer', 'min:4', 'max:14', function ($attribute, $value, $fail) {
                if ($value % 2 !== 0) {
                    $fail('The max teams must be an even number.');
                }
            }],
            'buy_in' => ['required', 'numeric', 'min:5'],
            'payout_structure' => ['required', 'array'],
            'roster_config' => ['required', 'array'],
            'roster_config.moneyline' => ['integer', 'min:0', 'max:4'],
            'roster_config.spread' => ['integer', 'min:0', 'max:4'],
            'roster_config.total' => ['integer', 'min:0', 'max:4'],
            'roster_config.player_prop' => ['integer', 'min:0', 'max:4'],
            'aggregate_odds_floor' => ['integer', 'max:-100'],
            'sports' => ['required', 'array', 'min:1'],
            'sports.*' => ['string', Rule::in(array_keys(config('draftslate.leagues.supported_sports')))],
            'draft_day' => ['integer', 'min:0', 'max:6'],
            'draft_time' => ['date_format:H:i:s'],
            'draft_timezone' => ['string', 'max:50'],
            'pick_timer_seconds' => ['integer', 'min:30', 'max:120'],
            'regular_season_weeks' => ['integer', 'min:8', 'max:18'],
            'playoff_format' => [Rule::in(['A', 'B', 'C', 'D'])],
            'team_name' => ['required', 'string', 'max:50'],
        ]);

        // Validate roster_config sum is 1-8
        $rosterSum = array_sum($validated['roster_config'] ?? []);
        if ($rosterSum < 1 || $rosterSum > 8) {
            return response()->json([
                'message' => 'Total starter slots must be between 1 and 8.',
                'errors' => ['roster_config' => ['Total starter slots must be between 1 and 8.']],
            ], 422);
        }

        $user = $request->user();

        // Enforce max leagues
        $activeCount = $user->leagues()
            ->where('state', '!=', 'cancelled')
            ->count();

        if ($activeCount >= $user->max_leagues) {
            return response()->json([
                'message' => 'You have reached your maximum number of leagues.',
                'errors' => ['max_leagues' => ['You have reached your maximum number of leagues.']],
            ], 422);
        }

        $teamName = $validated['team_name'];
        unset($validated['team_name']);

        $validated['commissioner_id'] = $user->id;

        if ($validated['type'] === 'private') {
            $validated['invite_code'] = League::generateInviteCode();
        }

        $league = League::create($validated);

        // Auto-join commissioner
        $league->memberships()->create([
            'user_id' => $user->id,
            'team_name' => $teamName,
        ]);

        // Create buy-in transaction (stubbed as completed)
        Transaction::create([
            'user_id' => $user->id,
            'league_id' => $league->id,
            'type' => 'buy_in',
            'amount' => $league->buy_in,
            'status' => 'completed',
            'notes' => 'League creation buy-in (stubbed)',
        ]);

        $league->loadCount('memberships');
        $league->load('commissioner');

        return response()->json([
            'data' => new LeagueResource($league),
        ], 201);
    }

    public function show(Request $request, League $league): JsonResponse
    {
        $league->load('commissioner');
        $league->loadCount('memberships');

        $data = (new LeagueResource($league))->toArray($request);

        // Include members if the user is a member
        if ($request->user() && $league->isMember($request->user())) {
            $data['members'] = LeagueMemberResource::collection(
                $league->memberships()->with('user')->get()
            );
        }

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, League $league): JsonResponse
    {
        if ($league->commissioner_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the commissioner can update league settings.'], 403);
        }

        if ($league->state !== 'pending') {
            return response()->json(['message' => 'League settings can only be changed while in pending state.'], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', Rule::in(['public', 'private'])],
            'max_teams' => ['sometimes', 'integer', 'min:4', 'max:14', 'even'],
            'buy_in' => ['sometimes', 'numeric', 'min:5'],
            'payout_structure' => ['sometimes', 'array'],
            'roster_config' => ['sometimes', 'array'],
            'roster_config.moneyline' => ['integer', 'min:0', 'max:4'],
            'roster_config.spread' => ['integer', 'min:0', 'max:4'],
            'roster_config.total' => ['integer', 'min:0', 'max:4'],
            'roster_config.player_prop' => ['integer', 'min:0', 'max:4'],
            'aggregate_odds_floor' => ['sometimes', 'integer', 'max:-100'],
            'sports' => ['sometimes', 'array', 'min:1'],
            'sports.*' => ['string', Rule::in(array_keys(config('draftslate.leagues.supported_sports')))],
            'draft_day' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'draft_time' => ['sometimes', 'date_format:H:i:s'],
            'draft_timezone' => ['sometimes', 'string', 'max:50'],
            'pick_timer_seconds' => ['sometimes', 'integer', 'min:30', 'max:120'],
            'regular_season_weeks' => ['sometimes', 'integer', 'min:8', 'max:18'],
            'playoff_format' => ['sometimes', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        // Validate roster_config sum if provided
        if (isset($validated['roster_config'])) {
            $rosterSum = array_sum($validated['roster_config']);
            if ($rosterSum < 1 || $rosterSum > 8) {
                return response()->json([
                    'message' => 'Total starter slots must be between 1 and 8.',
                    'errors' => ['roster_config' => ['Total starter slots must be between 1 and 8.']],
                ], 422);
            }
        }

        // Generate invite code if switching to private
        if (isset($validated['type']) && $validated['type'] === 'private' && !$league->invite_code) {
            $validated['invite_code'] = League::generateInviteCode();
        }

        $league->update($validated);
        $league->load('commissioner');
        $league->loadCount('memberships');

        return response()->json([
            'data' => new LeagueResource($league),
        ]);
    }

    public function destroy(Request $request, League $league): JsonResponse
    {
        if ($league->commissioner_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the commissioner can cancel the league.'], 403);
        }

        if ($league->state !== 'pending') {
            return response()->json(['message' => 'Only pending leagues can be cancelled.'], 422);
        }

        $league->update(['state' => 'cancelled']);

        return response()->json(['message' => 'League cancelled.']);
    }

    public function join(Request $request, League $league): JsonResponse
    {
        $validated = $request->validate([
            'team_name' => ['required', 'string', 'max:50'],
        ]);

        $user = $request->user();

        if ($league->state !== 'pending') {
            return response()->json(['message' => 'This league is no longer accepting members.'], 422);
        }

        if ($league->isFull()) {
            return response()->json(['message' => 'This league is full.'], 422);
        }

        if ($league->isMember($user)) {
            return response()->json(['message' => 'You are already a member of this league.'], 422);
        }

        // Enforce max leagues
        $activeCount = $user->leagues()
            ->where('state', '!=', 'cancelled')
            ->count();

        if ($activeCount >= $user->max_leagues) {
            return response()->json([
                'message' => 'You have reached your maximum number of leagues.',
                'errors' => ['max_leagues' => ['You have reached your maximum number of leagues.']],
            ], 422);
        }

        $league->memberships()->create([
            'user_id' => $user->id,
            'team_name' => $validated['team_name'],
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'league_id' => $league->id,
            'type' => 'buy_in',
            'amount' => $league->buy_in,
            'status' => 'completed',
            'notes' => 'League join buy-in (stubbed)',
        ]);

        $league->load('commissioner');
        $league->loadCount('memberships');

        return response()->json([
            'data' => new LeagueResource($league),
        ]);
    }

    public function leave(Request $request, League $league): JsonResponse
    {
        $user = $request->user();

        if ($league->state !== 'pending') {
            return response()->json(['message' => 'You can only leave a league while it is in pending state.'], 422);
        }

        if ($league->commissioner_id === $user->id) {
            return response()->json(['message' => 'The commissioner cannot leave the league. Cancel it instead.'], 422);
        }

        $membership = $league->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 422);
        }

        $membership->delete();

        Transaction::create([
            'user_id' => $user->id,
            'league_id' => $league->id,
            'type' => 'refund',
            'amount' => $league->buy_in,
            'status' => 'completed',
            'notes' => 'League leave refund (stubbed)',
        ]);

        return response()->json(['message' => 'You have left the league.']);
    }

    public function showByInviteCode(Request $request, string $inviteCode): JsonResponse
    {
        $league = League::where('invite_code', $inviteCode)
            ->where('state', 'pending')
            ->with('commissioner')
            ->withCount('memberships')
            ->firstOrFail();

        return response()->json([
            'data' => new LeagueResource($league),
        ]);
    }
}
