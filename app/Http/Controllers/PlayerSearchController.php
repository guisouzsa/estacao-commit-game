<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PlayerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = strtolower(trim((string) $request->query('q')));
        if (mb_strlen($query) < 2) {
            return response()->json(['players' => []]);
        }

        $pattern = '%'.addcslashes($query, '\\%_').'%';
        $players = User::query()
            ->where(function ($builder) use ($pattern): void {
                $builder->whereRaw('LOWER(username) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(display_name) LIKE ?', [$pattern]);
            })
            ->orderByRaw('LOWER(username)')
            ->limit(8)
            ->get(['id', 'username', 'display_name', 'avatar', 'profile_frame', 'profile_title'])
            ->map(fn (User $user) => [
                'username' => $user->username,
                'display_name' => $user->display_name ?: $user->username,
                ...PlayerPresenter::avatarFields($user),
                'profile_frame' => $user->profile_frame,
                'profile_title' => $user->profile_title,
            ]);

        return response()->json(['players' => $players]);
    }
}
