<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Notifications\NewFollower;
use App\Services\RoomStateBroadcaster;
use App\Support\PlayerPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seguir pessoas. Só contas permanentes (as rotas exigem o guard "web"):
 * visitantes não seguem nem são seguidos, e bots não têm perfil.
 */
class FollowController extends Controller
{
    private const PER_PAGE = 20;

    public function store(Request $request, string $username): RedirectResponse
    {
        $me = $request->user();
        $target = $this->findUser($username);

        if ($target->is($me)) {
            return $this->toast('info', 'Você não pode seguir a si mesmo.');
        }

        // INSERT ... ON CONFLICT DO NOTHING: dois cliques simultâneos geram uma única linha.
        $created = Follow::query()->insertOrIgnore([
            'follower_id' => $me->id,
            'followed_id' => $target->id,
            'created_at' => now(),
        ]) === 1;

        if (! $created) {
            return $this->toast('info', "Você já segue @{$target->username}.");
        }

        // Seguir, deixar de seguir e seguir de novo não gera avisos repetidos (janela de 24 h).
        if (Cache::add("follow-notice:{$me->id}:{$target->id}", 1, now()->addDay())) {
            $target->notify(NewFollower::from($me));
            app(RoomStateBroadcaster::class)->publishToActor($target);
        }

        return $this->toast('success', "Agora você segue @{$target->username}.");
    }

    public function destroy(Request $request, string $username): RedirectResponse
    {
        $target = $this->findUser($username);

        // Idempotente: deixar de seguir quem já não é seguido não é erro.
        Follow::query()
            ->where('follower_id', $request->user()->id)
            ->where('followed_id', $target->id)
            ->delete();

        return $this->toast('info', "Você deixou de seguir @{$target->username}.");
    }

    public function followers(Request $request, string $username): Response
    {
        return $this->connections($request, $username, 'followers');
    }

    public function following(Request $request, string $username): Response
    {
        return $this->connections($request, $username, 'following');
    }

    private function connections(Request $request, string $username, string $kind): Response
    {
        $profile = $this->findUser($username);
        $viewerId = (string) $request->user()->id;

        $query = User::query()->select('users.*');
        if ($kind === 'followers') {
            $query->join('follows', 'follows.follower_id', '=', 'users.id')
                ->where('follows.followed_id', $profile->id);
        } else {
            $query->join('follows', 'follows.followed_id', '=', 'users.id')
                ->where('follows.follower_id', $profile->id);
        }

        // Paginado: nunca carrega a lista inteira.
        $page = $query->orderByDesc('follows.created_at')->paginate(self::PER_PAGE)->withQueryString();

        // Uma única consulta diz quem desta página o visitante já segue (sem N+1).
        $iFollow = Follow::query()
            ->where('follower_id', $viewerId)
            ->whereIn('followed_id', $page->getCollection()->pluck('id'))
            ->pluck('followed_id')
            ->all();

        $page->through(fn (User $user) => [
            ...Arr::except(PlayerPresenter::user($user), ['key']),
            'is_following' => in_array($user->id, $iFollow, true),
            'is_me' => (string) $user->id === $viewerId,
        ]);

        return Inertia::render('Profile/Connections', [
            'profile' => ['username' => $profile->username],
            'kind' => $kind,
            'people' => $page,
            'counts' => [
                'followers' => Follow::query()->where('followed_id', $profile->id)->count(),
                'following' => Follow::query()->where('follower_id', $profile->id)->count(),
            ],
        ]);
    }

    private function findUser(string $username): User
    {
        return User::query()->whereRaw('LOWER(username) = ?', [Str::lower($username)])->firstOrFail();
    }

    private function toast(string $tone, string $message): RedirectResponse
    {
        return back()->with('toast', [
            'id' => (string) Str::uuid(),
            'tone' => $tone,
            'message' => $message,
        ]);
    }
}
