<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Notifications\AchievementUnlocked;
use App\Support\OpsLog;
use Illuminate\Support\Facades\DB;

/**
 * Concessão de conquistas. Tudo acontece no SERVIDOR, a partir de dados que o servidor gravou (cadastro, `users.matches_*`
 * e `match_results`); nenhum dado do navegador entra na decisão.
 *
 * Idempotência: a restrição única (user_id, badge_id) é a trava. A notificação só nasce se a linha da conquista
 * foi realmente criada agora (insertOrIgnore devolve 1), na mesma transação. Reabrir a página, entrar de novo,
 * outro aparelho ou duas partidas terminando juntas nunca duplicam a conquista nem o aviso.
 *
 * Custo: uma consulta traz as conquistas que a pessoa já tem; só as que faltam são comparadas, e a sequência de vitórias
 * lê no máximo `streak` linhas indexadas de `match_results`.
 */
class Achievements
{
    /** @return list<array<string, mixed>> */
    public static function catalog(): array
    {
        return (array) config('achievements.catalog');
    }

    /** @return array<string, mixed>|null */
    public static function definition(string $slug): ?array
    {
        foreach (self::catalog() as $item) {
            if ($item['slug'] === $slug) {
                return $item;
            }
        }

        return null;
    }

    /** Conquista de entrada na comunidade: cadastro concluído (e-mail/senha ou Google). */
    public function onRegistered(User $user): void
    {
        $this->evaluate($user, ['registered']);
    }

    /** Depois de gravar o resultado de uma partida concluída (chamado por MatchStatistics). */
    public function onMatchFinished(User $user): void
    {
        $this->evaluate($user, ['matches_played', 'wins', 'streak']);
    }

    /**
     * Confere os critérios e concede o que faltar. Falha aqui NUNCA derruba quem chamou (fim de partida, cadastro):
     * o erro é registrado e a próxima avaliação tenta de novo, pois os critérios são por total acumulado.
     *
     * @param  list<string>  $metrics
     * @return list<string> slugs concedidos agora
     */
    public function evaluate(User $user, array $metrics): array
    {
        try {
            return DB::transaction(fn () => $this->grantDue($user, $metrics)); // aninhada: vira savepoint
        } catch (\Throwable $e) {
            OpsLog::event('achievement.error', ['user' => $user->getKey(), 'error' => class_basename($e), 'message' => $e->getMessage()], 'error');

            return [];
        }
    }

    /** @return list<string> */
    private function grantDue(User $user, array $metrics): array
    {
        $owned = DB::table('badge_user')->join('badges', 'badges.id', '=', 'badge_user.badge_id')
            ->where('badge_user.user_id', $user->getKey())->pluck('badges.slug')->all();

        $granted = [];
        $streak = null;
        foreach (self::catalog() as $item) {
            if (! in_array($item['metric'], $metrics, true) || in_array($item['slug'], $owned, true)) {
                continue;
            }
            $reached = match ($item['metric']) {
                'registered' => true,
                'matches_played' => (int) $user->matches_played >= $item['threshold'],
                'wins' => (int) $user->matches_won >= $item['threshold'],
                'streak' => ($streak ??= $this->winStreak($user, 10)) >= $item['threshold'],
                default => false,
            };
            if ($reached && $this->grant($user, $item['slug'])) {
                $granted[] = $item['slug'];
            }
        }

        return $granted;
    }

    /** Vitórias consecutivas mais recentes (para no primeiro resultado que não é vitória). */
    private function winStreak(User $user, int $limit): int
    {
        $streak = 0;
        foreach (DB::table('match_results')->where('user_id', $user->getKey())->orderByDesc('finished_at')->limit($limit)->pluck('is_winner') as $won) {
            if (! in_array($won, [true, 1, '1', 't', 'true'], true)) { // PostgreSQL: bool; SQLite: 0/1
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /** Concede uma conquista específica, uma única vez. @return bool true se concedeu agora. */
    public function grant(User $user, string $slug): bool
    {
        $definition = self::definition($slug);
        if (! $definition) {
            return false;
        }

        $badge = Badge::query()->where('slug', $slug)->first() ?? Badge::query()->create([
            'slug' => $slug, 'name' => $definition['name'], 'description' => $definition['description'], 'category' => $definition['category'],
            'rarity' => $definition['rarity'], 'sort_order' => $definition['sort'],
        ]);

        $inserted = DB::table('badge_user')->insertOrIgnore([
            'user_id' => $user->getKey(), 'badge_id' => $badge->id, 'unlocked_at' => now(), 'granted_by' => 'system',
        ]);
        if ($inserted !== 1) {
            return false; // já tinha: nada de aviso repetido
        }

        $user->notify(AchievementUnlocked::for($definition));
        OpsLog::event('achievement.unlocked', ['user' => $user->getKey(), 'slug' => $slug]);
        app(RoomStateBroadcaster::class)->publishToActor($user); // depois do commit; quem está online vê o aviso sem recarregar

        return true;
    }
}
