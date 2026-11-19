<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $authUser = $user?->only([
            'id',
            'username',
            'display_name',
            'bio',
            'avatar',
            'avatar_position_x',
            'avatar_position_y',
            'avatar_zoom',
            'profile_cover',
            'cover_position_x',
            'cover_position_y',
            'profile_frame',
            'primary_badge',
            'profile_title',
            'email',
            'email_verified_at',
            'created_at',
        ]);

        if ($user && $authUser) {
            $authUser['avatar'] = $user->avatar
                ? (str_starts_with($user->avatar, 'http')
                    ? $user->avatar
                    : Storage::disk('public')->url($user->avatar))
                : null;
            $authUser['cover'] = $user->profile_cover
                ? Storage::disk('public')->url($user->profile_cover)
                : null;
            $authUser['developer_badge'] = $user->developer_badge
                ? config('profiles.developer_badges.'.$user->developer_badge)
                : null;
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authUser,
            ],
        ];
    }
}
