<?php

namespace App\Services;

use App\Models\User;
use App\Support\PlayerPresenter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Jogadores recomendados da Dashboard.
 *
 * - O perfil "gui" vem SEMPRE em primeiro (quando existe e não é quem está vendo).
 * - Os demais saem de um sorteio sem repetição: cada pessoa aparece no máximo uma vez por lista.
 * - Rodízio justo entre recarregamentos: quem acabou de aparecer só volta depois que os que ainda
 *   não apareceram forem exibidos (memória curta na sessão). Não é previsível: a ordem dentro de
 *   cada grupo é sempre sorteada. Quando o grupo de "novos" acaba, completa com os mais antigos.
 * - Barato: uma consulta sorteia só ids; os dados completos (que incluem a foto) vêm só dos escolhidos.
 */
class RecommendedPlayers
{
    public const INITIAL = 5;

    public const PAGE_LIMIT = 20;

    /** @return Collection<int, User> */
    public function forPage(?Authenticatable $actor): Collection
    {
        $key = $this->pageSessionKey($actor);
        $ids = session($key);
        if (! is_array($ids)) {
            $gui = $this->gui($actor);
            $users = $this->pick($actor, self::PAGE_LIMIT - ($gui ? 1 : 0), $gui ? [$gui->id] : []);
            $users = $gui ? $users->prepend($gui)->values() : $users;
            $ids = $users->pluck('id')->all();
            session([$key => $ids]);
            $this->remember($ids);

            return $users;
        }

        $users = User::query()->whereIn('id', $ids)
            ->when($this->viewerId($actor), fn ($query, $id) => $query->whereKeyNot($id))
            ->get($this->columns())->keyBy('id');

        return collect($ids)->map(fn ($id) => $users->get($id))->filter()->values();
    }

    public function refreshOnNextVisit(?Authenticatable $actor): void
    {
        session()->forget($this->pageSessionKey($actor));
    }

    private function pageSessionKey(?Authenticatable $actor): string
    {
        return 'recommended.page.'.($this->viewerId($actor) ?? 'guest');
    }

    /** Quantos ids o sorteio considera (evita ordenar a tabela inteira nem carregar fotos à toa). */
    private const POOL = 120;

    /** Quantos ids recém-mostrados a sessão lembra. */
    private const MEMORY = 40;

    private const SESSION_KEY = 'recommended.recent';

    /**
     * Seleção inicial (5, com o gui no topo). Atualiza a memória de quem já apareceu.
     *
     * @return Collection<int, User>
     */
    public function initial(?Authenticatable $actor): Collection
    {
        $gui = $this->gui($actor);
        $others = $this->pick($actor, self::INITIAL - ($gui ? 1 : 0), $gui ? [$gui->id] : []);
        $this->remember($others->pluck('id')->all());

        return $gui ? $others->prepend($gui)->values() : $others;
    }

    /** @return array<string, mixed> */
    public static function present(User $user): array
    {
        return [
            // Sem `key` (que carrega o ID): a Dashboard só precisa exibir o jogador.
            ...Arr::except(PlayerPresenter::user($user), ['key']),
            'profile_title' => $user->profile_title,
        ];
    }

    private function gui(?Authenticatable $actor): ?User
    {
        $gui = User::query()->whereRaw('LOWER(username) = ?', ['gui'])->first($this->columns());

        return $gui && $gui->getKey() !== $this->viewerId($actor) ? $gui : null;
    }

    /**
     * @param  list<string>  $exclude
     * @param  list<string>  $excludeUsernames
     * @return Collection<int, User>
     */
    private function pick(?Authenticatable $actor, int $count, array $exclude, array $excludeUsernames = []): Collection
    {
        if ($count <= 0) {
            return collect();
        }
        $viewer = $this->viewerId($actor);
        $excluded = array_values(array_unique(array_filter([...$exclude, $viewer])));

        // 1) Só ids, já em ordem aleatória.
        $ids = User::query()
            ->whereNotIn('id', $excluded)
            ->when($excludeUsernames !== [], fn ($q) => $q->whereNotIn('username', $excludeUsernames))
            ->inRandomOrder()->limit(self::POOL)->pluck('id')->all();
        if ($ids === []) {
            return collect();
        }

        // 2) Rodízio: primeiro quem NÃO apareceu há pouco; depois os que apareceram, do mais antigo.
        $recent = (array) session(self::SESSION_KEY, []);
        $fresh = array_values(array_diff($ids, $recent));
        $stale = array_values(array_filter($recent, fn ($id) => in_array($id, $ids, true))); // do mais antigo ao mais novo
        $chosen = array_slice([...$fresh, ...$stale], 0, $count);

        // 3) Dados completos só dos escolhidos, na ordem sorteada.
        $users = User::query()->whereIn('id', $chosen)->get($this->columns())->keyBy('id');

        return collect($chosen)->map(fn ($id) => $users->get($id))->filter()->values();
    }

    /** @param  list<string>  $ids */
    private function remember(array $ids): void
    {
        $recent = array_values(array_diff((array) session(self::SESSION_KEY, []), $ids));
        session([self::SESSION_KEY => array_slice([...$recent, ...$ids], -self::MEMORY)]);
    }

    private function viewerId(?Authenticatable $actor): ?string
    {
        return $actor instanceof User ? (string) $actor->getKey() : null;
    }

    /** @return list<string> */
    private function columns(): array
    {
        return ['id', 'username', 'avatar', 'profile_frame', 'profile_background', 'profile_title', 'verified_at'];
    }
}
