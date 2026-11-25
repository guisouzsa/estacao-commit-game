<?php

namespace App\Support;

use App\Models\Badge;
use App\Models\User;
use App\Services\Achievements;
use Illuminate\Support\Facades\DB;

/**
 * Lista de conquistas para o perfil: catálogo (config/achievements.php) + o que a pessoa desbloqueou.
 * Duas consultas pequenas (catálogo ativo e desbloqueios da pessoa), sem N+1, e só quando a página do perfil é montada.
 */
final class AchievementPresenter
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        $rows = Badge::query()->whereRaw('active IS TRUE')->whereRaw('secret IS FALSE')->orderBy('sort_order')->get();
        $mine = DB::table('badge_user')->where('user_id', $user->getKey())->get(['badge_id', 'unlocked_at', 'featured_order'])->keyBy('badge_id');

        return $rows->map(function (Badge $badge) use ($mine): array {
            $definition = Achievements::definition($badge->slug) ?? [];
            $own = $mine->get($badge->id);

            return [
                'id' => $badge->id,
                'slug' => $badge->slug,
                'name' => $badge->name,
                'description' => $badge->description,
                'category' => $badge->category,
                'rarity' => $badge->rarity,
                'tier' => $definition['tier'] ?? 'starter',
                'icon' => $definition['icon'] ?? 'star',
                'criterion' => $definition['criterion'] ?? $badge->description,
                'unlocked' => $own !== null,
                'unlocked_at' => $own?->unlocked_at,
                'featured_order' => $own?->featured_order,
            ];
        })->values()->all();
    }
}
