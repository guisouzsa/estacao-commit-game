<?php

namespace App\Http\Controllers;

use App\Support\GuestIdentity;
use App\Support\PlayerNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * "Jogar agora": entrar sem criar conta. O visitante escolhe nome e avatar e
 * recebe um perfil temporário. A identidade fica num cookie HttpOnly.
 */
class GuestPlayController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->alreadyPlaying($request)) {
            return to_route('dashboard');
        }

        return Inertia::render('Guest/Create', [
            'styles' => config('players.selectable_avatar_styles'),
            'name' => config('players.name'),
        ]);
    }

    /** Verificação de disponibilidade enquanto a pessoa digita (limitada por rate limit na rota). */
    public function availability(Request $request, PlayerNames $names): JsonResponse
    {
        $name = PlayerNames::display((string) $request->query('name', ''));
        $problem = $name === '' ? null : $names->problem($name);

        return response()->json([
            'available' => $name !== '' && $problem === null,
            'message' => $problem,
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, GuestIdentity $identity): SymfonyResponse
    {
        if ($this->alreadyPlaying($request)) {
            return to_route('dashboard');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'avatar_style' => ['required', Rule::in(config('players.selectable_avatar_styles'))],
            'avatar_seed' => ['required', 'string', 'regex:/^[a-z0-9]{6,32}$/'],
        ], [
            'name.required' => 'Escolha um nome para jogar.',
            'avatar_style.in' => 'Escolha um dos estilos de avatar disponíveis.',
            'avatar_seed.regex' => 'Avatar inválido. Escolha outro.',
        ]);

        ['profile' => $profile, 'token' => $token] = $identity->create(
            $data['name'],
            $data['avatar_style'],
            $data['avatar_seed'],
        );

        $request->session()->flash('toast', [
            'id' => (string) Str::uuid(),
            'tone' => 'success',
            'message' => "Bem-vindo, {$profile->display_name}! Crie uma sala ou entre com um convite.",
        ]);

        // Navegação COMPLETA (e não uma visita em XHR do Inertia): o navegador envia o
        // cookie recém-criado com certeza, e qualquer falha aparece como página de erro
        // em vez de deixar a pessoa parada no formulário sem explicação.
        $pendingInvite = $request->session()->pull('pending_room_invite');
        $response = Inertia::location($pendingInvite ? route('invites.open', $pendingInvite) : route('dashboard'));
        $response->headers->setCookie($identity->cookie($request, $token));

        return $response;
    }

    /** Sair do jogo: apaga o perfil temporário (libera o nome) e o cookie. */
    public function destroy(Request $request, GuestIdentity $identity): RedirectResponse
    {
        $guest = Auth::guard('visitor')->user();
        abort_unless($guest, 401);
        abort_unless(
            $guest->newQuery()->whereKey($guest->getKey())->deletable()->exists(),
            409,
            'Seu perfil temporário está ligado a uma sala ou partida ativa. Saia ou encerre a sala antes de apagar o perfil.',
        );
        $guest->delete();

        return redirect('/')->withCookie($identity->forgetCookie());
    }

    private function alreadyPlaying(Request $request): bool
    {
        return $request->user() !== null || Auth::guard('visitor')->check();
    }
}
