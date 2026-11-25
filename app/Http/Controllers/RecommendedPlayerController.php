<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Services\RecommendedPlayers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecommendedPlayerController extends Controller
{
    public function __invoke(Request $request, RecommendedPlayers $recommended): Response
    {
        $viewer = $request->user();
        $viewerId = $viewer instanceof User ? (string) $viewer->id : null;

        $people = $recommended->forPage($viewer);

        $following = $viewerId
            ? Follow::query()
                ->where('follower_id', $viewerId)
                ->whereIn('followed_id', $people->pluck('id'))
                ->pluck('followed_id')
                ->map(fn ($id) => (string) $id)
                ->all()
            : [];

        $people = $people->map(fn (User $user) => [
            ...RecommendedPlayers::present($user),
            'is_following' => in_array((string) $user->id, $following, true),
            'can_follow' => $viewerId !== null,
        ]);

        return Inertia::render('Players/Recommended', ['people' => ['data' => $people]]);
    }

    public function refresh(Request $request, RecommendedPlayers $recommended): RedirectResponse
    {
        $recommended->refreshOnNextVisit($request->user());

        return to_route('players.recommended');
    }
}
