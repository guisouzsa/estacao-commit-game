<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Quais conquistas a pessoa destaca no perfil (limite em config/achievements.php). Só as que ela realmente desbloqueou. */
class ProfileAchievementController extends Controller
{
    public function featured(Request $request): RedirectResponse
    {
        $limit = max(1, (int) config('achievements.featured_limit'));
        $data = $request->validate([
            'featured' => ['present', 'array', "max:{$limit}"],
            'featured.*' => ['integer', 'distinct'],
        ], ['featured.max' => "Você pode destacar até {$limit} conquistas."]);

        $user = $request->user();
        $ids = array_values($data['featured']);

        // A autorização é do servidor: cada id precisa estar entre os desbloqueios DESTA pessoa.
        $owned = DB::table('badge_user')->where('user_id', $user->getKey())->whereIn('badge_id', $ids ?: [0])->pluck('badge_id')->all();
        if (count($owned) !== count($ids)) {
            throw ValidationException::withMessages(['featured' => 'Só é possível destacar conquistas já desbloqueadas.']);
        }

        DB::transaction(function () use ($user, $ids): void {
            DB::table('badge_user')->where('user_id', $user->getKey())->update(['featured_order' => null]);
            foreach ($ids as $position => $badgeId) {
                DB::table('badge_user')->where('user_id', $user->getKey())->where('badge_id', $badgeId)->update(['featured_order' => $position + 1]);
            }
            // Compatibilidade com o campo antigo (uma insígnia em destaque): espelha a primeira.
            $user->forceFill(['achievement_badge_id' => $ids[0] ?? null])->save();
        });

        return back()->with('status', 'Destaques atualizados.');
    }
}
