<?php

namespace App\Http\Middleware;

use App\Http\Controllers\NotificationController;
use App\Models\GuestProfile;
use App\Models\User;
use App\Support\Actor;
use App\Support\AvatarUrl;
use App\Support\PlayerPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        // Conta permanente ou visitante. Em rotas públicas o guard padrão (web)
        // não conhece o visitante, então ele é consultado à parte (sem cookie, sem consulta).
        $actor = $request->user() ?? Auth::guard('visitor')->user();
        // Contador de avisos não lidos: calculado no máximo UMA vez por resposta e só se alguma prop o pedir.
        $count = null;
        $unread = function () use ($actor, &$count): int {
            return $count ??= $actor instanceof User
                ? $actor->unreadNotifications()->whereIn('type', NotificationController::TYPES)->count()
                : 0;
        };

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $this->authUser($actor, $request),
                'kind' => $actor ? Actor::kind($actor) : null,
                // Canal privado para avisos em tempo real desta pessoa.
                'channel' => $actor ? Actor::channel($actor) : null,
                // Só contas têm central de notificações. Closure: uma consulta por resposta
                // de página, e nenhuma em recarregamentos parciais que não pedem "auth".
                'unread_notifications' => $unread,
                // Popup de conquista: só as ainda NÃO lidas (o front as marca como lidas ao exibir, então não repetem).
                // Só consulta quando há avisos não lidos (o contador acima já foi calculado).
                'achievement_popups' => fn () => $actor instanceof User && $unread() > 0
                    ? $actor->unreadNotifications()->where('type', \App\Notifications\AchievementUnlocked::class)->latest()->limit(3)->get()
                        ->map(fn ($n) => ['id' => $n->id, ...\Illuminate\Support\Arr::only($n->data, ['slug', 'name', 'description', 'rarity', 'tier', 'icon'])])->all()
                    : [],
            ],
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function authUser(mixed $actor, Request $request): ?array
    {
        if ($actor instanceof GuestProfile) {
            // Visitante: só o necessário para exibir nome e avatar. Sem e-mail nem dados de conta.
            return [...PlayerPresenter::guest($actor), 'id' => $actor->id, 'kind' => 'guest'];
        }

        if (! $actor instanceof User) {
            return null;
        }

        $authUser = $actor->only([
            'id',
            'username',
            'bio',
            'avatar_position_x',
            'avatar_position_y',
            'avatar_zoom',
            'cover_position_x',
            'cover_position_y',
            'cover_zoom',
            'profile_frame',
            'profile_background',
            'primary_badge',
            'profile_title',
            'email',
            'email_verified_at',
            'created_at',
            'onboarding_completed_at',
        ]);

        // Mesmo formato de avatar em toda a aplicação (URL relativa, com fallback por semente).
        $authUser = [...$authUser, ...PlayerPresenter::avatarFields($actor)];
        $authUser['kind'] = 'user';
        // A capa pode pesar centenas de KB (a imagem fica no banco) e só a tela de
        // edição do perfil a usa: não vai no JSON de todas as outras páginas.
        $authUser['cover'] = $actor->profile_cover && $request->routeIs('profile.*')
            ? AvatarUrl::forUser($actor, 'cover')
            : null;
        $authUser['is_verified'] = $actor->isVerified();
        $authUser['developer_badge'] =$actor->developer_badge
            ? config('profiles.developer_badges.'.$actor->developer_badge)
            : null;

        return $authUser;
    }
}
