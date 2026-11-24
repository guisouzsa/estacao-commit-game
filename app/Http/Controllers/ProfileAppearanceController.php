<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileAppearanceUpdateRequest;
use App\Support\ProfileImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProfileAppearanceController extends Controller
{
    public function update(
        ProfileAppearanceUpdateRequest $request,
        ProfileImageOptimizer $imageOptimizer
    ): RedirectResponse {
        $user = $request->user();
        $validated = $request->validated();
        if (isset($validated['username'])) {
            $user->username = strtolower($validated['username']);
            $user->display_name = $user->username;
        }
        if (array_key_exists('bio', $validated)) {
            $user->bio = $validated['bio'];
        }

        if ($request->hasFile('avatar')) {
            $user->avatar = $imageOptimizer->encode($request->file('avatar'), 800);
        } elseif (! empty($validated['avatar_generated'])) {
            // Ícone gerado no lugar da foto: guardado como "gen:estilo:semente".
            $user->avatar = 'gen:'.$validated['avatar_generated'];
        } elseif ($validated['remove_avatar'] ?? false) {
            $user->avatar = null;
        }

        if ($request->hasFile('cover')) {
            $user->profile_cover = $imageOptimizer->encode($request->file('cover'), 1920);
        } elseif ($validated['remove_cover'] ?? false) {
            $user->profile_cover = null;
        }

        $user->avatar_position_x = $validated['avatar_position_x'];
        $user->avatar_position_y = $validated['avatar_position_y'];
        $user->avatar_zoom = $validated['avatar_zoom'] ?? $user->avatar_zoom ?? 100;
        $user->cover_position_x = $validated['cover_position_x'] ?? $user->cover_position_x ?? 50;
        $user->cover_position_y = $validated['cover_position_y'] ?? $user->cover_position_y ?? 50;
        $user->profile_frame = $validated['profile_frame'];
        $user->profile_background = $validated['profile_background'] ?? null;
        $user->primary_badge = $validated['primary_badge'];
        $user->profile_title = $validated['profile_title'] ?? null;
        $user->achievement_badge_id = $validated['achievement_badge_id'] ?? null;
        DB::transaction(function () use ($user, $validated): void {
            $user->save();
            if (array_key_exists('links', $validated)) {
                $user->profileLinks()->delete();
                foreach ($validated['links'] as $order => $link) {
                    $user->profileLinks()->create([...$link, 'sort_order' => $order]);
                }
            }
        });

        return back()->with('status', 'A aparência do perfil foi atualizada.');
    }
}
