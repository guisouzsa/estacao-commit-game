<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load(['badges' => fn ($query) => $query->whereRaw('active IS TRUE'), 'profileLinks', 'achievementBadge']);
        return Inertia::render('Profile/Edit', [
            'status' => session('status'),
            'avatarStyles' => config('players.selectable_avatar_styles'),
            'googleLinked' => filled($request->user()->google_id),
            'appearanceOptions' => [
                'frames' => config('profiles.frames'),
                'badges' => config('profiles.badges'),
                'titles' => config('profiles.titles'),
            ],
            'profileExtras' => [
                'links' => $user->profileLinks->map->only(['platform','label','url','is_public'])->values(),
                'badges' => \App\Models\Badge::query()->whereRaw('active IS TRUE')->whereRaw('secret IS FALSE')->orderBy('sort_order')->get()->map(fn ($badge) => [
                    ...$badge->only(['id','slug','name','description','category','rarity','image_path']),
                    'unlocked' => $user->badges->contains($badge->id),
                    'unlocked_at' => optional($user->badges->firstWhere('id', $badge->id)?->pivot)->unlocked_at,
                ]),
                'achievement_badge_id' => $user->achievement_badge_id,
                'statistics' => $user->historicalGameStatistics(),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['username'] = strtolower($validated['username']);
        $request->user()->fill($validated);
        $request->user()->display_name = $validated['username'];

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
