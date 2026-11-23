<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AvatarUrl;
use App\Support\PlayerPresenter;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Badge;

class PublicProfileController extends Controller
{
    public function show(string $username): Response
    {
        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->with(['badges' => fn ($query) => $query->whereRaw('active IS TRUE'), 'profileLinks' => fn ($query) => $query->whereRaw('is_public IS TRUE'), 'achievementBadge'])
            ->firstOrFail();

        return Inertia::render('Profile/Public', [
            'profile' => [
                'username' => $user->username,
                'bio' => $user->bio,
                'avatar' => AvatarUrl::for($user->avatar),
                'avatar_style' => 'pixel',
                'avatar_seed' => PlayerPresenter::seed((string) $user->id),
                'avatar_position_x' => $user->avatar_position_x ?? 50,
                'avatar_position_y' => $user->avatar_position_y ?? 50,
                'avatar_zoom' => $user->avatar_zoom ?? 100,
                'cover' => AvatarUrl::for($user->profile_cover),
                'cover_position_x' => $user->cover_position_x ?? 50,
                'cover_position_y' => $user->cover_position_y ?? 50,
                'profile_frame' => $user->profile_frame ?? 'standard',
                'primary_badge' => $user->primary_badge ?? 'terminal',
                'profile_title' => $user->profile_title ?? 'explorer',
                'developer_badge' => $user->developer_badge
                    ? config('profiles.developer_badges.'.$user->developer_badge)
                    : null,
                'created_at' => $user->created_at,
                'links' => $user->profileLinks->map->only(['platform','label','url'])->values(),
                'badges' => Badge::query()->whereRaw('active IS TRUE')->whereRaw('secret IS FALSE')->orderBy('sort_order')->get()->map(fn ($badge) => [
                    ...$badge->only(['id','slug','name','description','category','rarity','image_path']),
                    'unlocked' => $user->badges->contains($badge->id),
                    'unlocked_at' => optional($user->badges->firstWhere('id', $badge->id)?->pivot)->unlocked_at,
                ]),
                'achievement_badge' => $user->achievementBadge?->only(['id','slug','name','description','rarity','image_path']),
                'statistics' => $user->historicalGameStatistics(),
            ],
        ]);
    }
}
