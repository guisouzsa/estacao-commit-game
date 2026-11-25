<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\AchievementPresenter;
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
        $user = $request->user()->load('profileLinks');

        return Inertia::render('Profile/Edit', [
            'status' => session('status'),
            'avatarStyles' => config('players.selectable_avatar_styles'),
            'appearanceOptions' => [
                'frames' => config('profiles.frames'),
                'backgrounds' => config('profiles.backgrounds'),
                'badges' => config('profiles.badges'),
                'titles' => config('profiles.titles'),
            ],
            'profileExtras' => [
                'links' => $user->profileLinks->map->only(['platform', 'label', 'url', 'is_public'])->values(),
                'badges' => AchievementPresenter::forUser($user),
                'achievement_display_mode' => $user->achievement_display_mode ?? 'none',
                'statistics' => $user->historicalGameStatistics(),
            ],
        ]);
    }

    /** Configurações da conta (e-mail, Google, senha, exclusão e, só para quem tem permissão, a verificação de jogadores). */
    public function settings(Request $request): Response
    {
        return Inertia::render('Profile/Settings', [
            'status' => session('status'),
            'googleLinked' => filled($request->user()->google_id),
            'canManageVerified' => $request->user()->can('manage-verified'),
            'googleConfigured' => filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret'))
                && filled(config('services.google.redirect')),
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

        return Redirect::back(302, [], route('profile.edit'));
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
