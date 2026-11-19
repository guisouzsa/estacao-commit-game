<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileAppearanceUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Illuminate\Support\Facades\DB;

class ProfileAppearanceController extends Controller
{
    public function update(ProfileAppearanceUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $previousAvatar = $user->avatar;
        $previousCover = $user->profile_cover;
        if (isset($validated['username'])) {
            $user->username = strtolower($validated['username']);
            // Compatibilidade com registros antigos; a identidade pública usa somente username.
            $user->display_name = $user->username;
        }
        if (array_key_exists('bio', $validated)) {
            $user->bio = $validated['bio'];
        }

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->storePublicly("avatars/{$user->id}", 'public');

            if ($avatarPath === false) {
                throw new RuntimeException('Não foi possível armazenar o avatar.');
            }

            $user->avatar = $avatarPath;
        } elseif ($validated['remove_avatar'] ?? false) {
            $user->avatar = null;
        }

        if ($request->hasFile('cover')) {
            $coverPath = $request->file('cover')->storePublicly("covers/{$user->id}", 'public');
            if ($coverPath === false) {
                throw new RuntimeException('Não foi possível armazenar a capa.');
            }
            $user->profile_cover = $coverPath;
        } elseif ($validated['remove_cover'] ?? false) {
            $user->profile_cover = null;
        }

        $user->avatar_position_x = $validated['avatar_position_x'];
        $user->avatar_position_y = $validated['avatar_position_y'];
        $user->avatar_zoom = $validated['avatar_zoom'] ?? $user->avatar_zoom ?? 100;
        $user->cover_position_x = $validated['cover_position_x'] ?? $user->cover_position_x ?? 50;
        $user->cover_position_y = $validated['cover_position_y'] ?? $user->cover_position_y ?? 50;
        $user->profile_frame = $validated['profile_frame'];
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

        if ($previousAvatar !== $user->avatar && $this->isManagedAvatar($previousAvatar, $user->id)) {
            Storage::disk('public')->delete($previousAvatar);
        }
        if ($previousCover !== $user->profile_cover && $this->isManagedCover($previousCover, $user->id)) {
            Storage::disk('public')->delete($previousCover);
        }

        return back()->with('status', 'A aparência do perfil foi atualizada.');
    }

    private function isManagedAvatar(?string $path, string $userId): bool
    {
        return $path !== null && str_starts_with($path, "avatars/{$userId}/");
    }

    private function isManagedCover(?string $path, string $userId): bool
    {
        return $path !== null && str_starts_with($path, "covers/{$userId}/");
    }
}
