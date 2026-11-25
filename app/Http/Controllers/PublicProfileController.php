<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Support\AchievementPresenter;
use App\Support\AvatarUrl;
use App\Support\PlayerPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicProfileController extends Controller
{
    public function show(Request $request, string $username): Response
    {
        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->with(['profileLinks' => fn ($query) => $query->whereRaw('is_public IS TRUE')])
            ->firstOrFail();

        $viewer = $request->user();
        $isMe = $viewer instanceof User && $viewer->is($user);
        $displayMode = $user->achievement_display_mode ?? 'none';

        return Inertia::render('Profile/Public', [
            'social' => [
                'followers_count' => Follow::query()->where('followed_id', $user->id)->count(),
                'following_count' => Follow::query()->where('follower_id', $user->id)->count(),
                // Só contas permanentes seguem. Visitantes e anônimos veem os números.
                'logged_in' => $viewer instanceof User,
                'can_follow' => $viewer instanceof User && ! $isMe,
                'is_me' => $isMe,
                'is_following' => $viewer instanceof User && ! $isMe
                    && Follow::query()->where('follower_id', $viewer->id)->where('followed_id', $user->id)->exists(),
            ],
            'profile' => [
                'username' => $user->username,
                'bio' => $user->bio,
                ...PlayerPresenter::avatarFields($user),
                'avatar_position_x' => $user->avatar_position_x ?? 50,
                'avatar_position_y' => $user->avatar_position_y ?? 50,
                'avatar_zoom' => $user->avatar_zoom ?? 100,
                'cover' => AvatarUrl::forUser($user, 'cover'),
                'cover_position_x' => $user->cover_position_x ?? 50,
                'cover_position_y' => $user->cover_position_y ?? 50,
                'cover_zoom' => $user->cover_zoom ?? 100,
                'profile_frame' => $user->profile_frame ?? 'standard',
                'profile_background' => $user->profile_background,
                'primary_badge' => $user->primary_badge ?? 'terminal',
                'profile_title' => $user->profile_title ?? 'explorer',
                'is_leader' => $user->isLeader(),
                'developer_badge' => $user->developer_badge
                    ? config('profiles.developer_badges.'.$user->developer_badge)
                    : null,
                'created_at' => $user->created_at,
                'links' => $user->profileLinks->map->only(['platform', 'label', 'url'])->values(),
                'badges' => $displayMode === 'none' ? [] : array_values(array_filter(
                    AchievementPresenter::forUser($user),
                    fn (array $badge) => $badge['displayed'],
                )),
                'achievement_display_mode' => $displayMode,
                'statistics' => $user->historicalGameStatistics(),
            ],
        ]);
    }
}
