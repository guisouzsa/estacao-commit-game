<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $rooms = GameRoom::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $request->user()->id))
            ->withCount('participants')
            ->latest()
            ->limit(10)
            ->get(['id','host_user_id','status','created_at']);

        return Inertia::render('Dashboard', [
            'rooms' => $rooms,
            'inviteUrl' => session('invite_url'),
            'recommendedPlayers' => User::query()
                ->orderByRaw("CASE WHEN LOWER(username) = 'gui' THEN 0 ELSE 1 END")
                ->inRandomOrder()
                ->limit(5)
                ->get(['username','avatar','profile_frame','profile_title'])
                ->sortBy(fn (User $user) => strtolower($user->username) === 'gui' ? 0 : 1)
                ->values()
                ->map(fn (User $user) => [
                    'username'=>$user->username,
                    'avatar'=>$user->avatar ? (str_starts_with($user->avatar, 'http') ? $user->avatar : Storage::disk('public')->url($user->avatar)) : null,
                    'profile_frame'=>$user->profile_frame,
                    'profile_title'=>$user->profile_title,
                ]),
        ]);
    }
}
