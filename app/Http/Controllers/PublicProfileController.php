<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Badge;

class PublicProfileController extends Controller
{
    public function show(string $username): Response
    {
        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->with(['badges' => fn ($query) => $query->where('active', true), 'profileLinks' => fn ($query) => $query->where('is_public', true), 'achievementBadge'])
            ->firstOrFail();

        return Inertia::render('Profile/Public', [
            'profile' => [
                'username' => $user->username,
                'display_name' => $user->display_name,
                'bio' => $user->bio,
                'avatar' => $user->avatar
                    ? (str_starts_with($user->avatar, 'http')
                        ? $user->avatar
                        : Storage::disk('public')->url($user->avatar))
                    : null,
                'avatar_position_x' => $user->avatar_position_x ?? 50,
                'avatar_position_y' => $user->avatar_position_y ?? 50,
                'avatar_zoom' => $user->avatar_zoom ?? 100,
                'cover' => $user->profile_cover ? Storage::disk('public')->url($user->profile_cover) : null,
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
                'badges' => Badge::query()->where('active', true)->where('secret', false)->orderBy('sort_order')->get()->map(fn ($badge) => [
                    ...$badge->only(['id','slug','name','description','category','rarity','image_path']),
                    'unlocked' => $user->badges->contains($badge->id),
                    'unlocked_at' => optional($user->badges->firstWhere('id', $badge->id)?->pivot)->unlocked_at,
                ]),
                'achievement_badge' => $user->achievementBadge?->only(['id','slug','name','description','rarity','image_path']),
                'statistics' => null,
            ],
        ]);
    }
}
