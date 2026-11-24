<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Services\RecommendedPlayers;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class RecommendedPlayerController extends Controller
{
    private const PER_PAGE = 20;

    public function __invoke(Request $request): Response
    {
        $viewer = $request->user();
        $viewerId = $viewer instanceof User ? (string) $viewer->id : null;

        $people = User::query()
            ->when($viewerId, fn ($query) => $query->whereKeyNot($viewerId))
            ->orderByRaw("CASE WHEN LOWER(username) = 'gui' THEN 0 ELSE 1 END")
            ->orderBy('username')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $following = $viewerId
            ? Follow::query()
                ->where('follower_id', $viewerId)
                ->whereIn('followed_id', $people->getCollection()->pluck('id'))
                ->pluck('followed_id')
                ->map(fn ($id) => (string) $id)
                ->all()
            : [];

        $people->through(fn (User $user) => [
            ...Arr::except(RecommendedPlayers::present($user), ['key']),
            'is_following' => in_array((string) $user->id, $following, true),
            'can_follow' => $viewerId !== null,
        ]);

        return Inertia::render('Players/Recommended', ['people' => $people]);
    }
}
