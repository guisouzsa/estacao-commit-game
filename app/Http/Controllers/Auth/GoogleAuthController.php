<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return Redirect::route('login')->with('status', 'A autenticação com Google ainda não está configurada.');
        }

        $request->session()->forget(['google_oauth_action', 'pending_google_user']);

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function link(Request $request): Response
    {
        if (! $this->isConfigured()) {
            return Redirect::route('profile.edit')->with('status', 'A autenticação com Google ainda não está configurada.');
        }

        $request->session()->forget('pending_google_user');
        $request->session()->put('google_oauth_action', 'link');

        $url = Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect()
            ->getTargetUrl();

        return Inertia::location($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            $request->session()->forget(['google_oauth_action', 'pending_google_user']);

            return $this->oauthError($request, 'A autenticação com Google foi cancelada ou não foi autorizada.');
        }

        $action = $request->session()->pull('google_oauth_action', 'login');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            $request->session()->forget('pending_google_user');

            return $this->oauthError($request, 'Não foi possível validar a resposta do Google. Tente novamente.');
        }

        $rawUser = $googleUser->getRaw();
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $googleId = trim((string) $googleUser->getId());
        $emailVerified = filter_var(
            $rawUser['email_verified'] ?? $rawUser['verified_email'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $emailVerified || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $googleId === '') {
            return $this->oauthError($request, 'O Google não forneceu um e-mail verificado válido para esta conta.');
        }

        if ($action === 'link') {
            return $this->linkAccount($request, $googleId, $email);
        }

        $user = User::where('google_id', $googleId)->first();

        if ($user) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        }

        // O Google já provou a posse deste e-mail (email_verified): quem tem conta com ele entra por aqui
        // e a conta passa a aceitar o Google também.
        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing) {
            return $this->signInExisting($request, $existing, $googleId);
        }

        $displayName = trim((string) $googleUser->getName());

        $request->session()->put('pending_google_user', [
            'google_id' => $googleId,
            'email' => $email,
            'display_name' => $displayName === '' ? '' : mb_substr($displayName, 0, 50),
        ]);

        return redirect()->route('google.username');
    }

    private function signInExisting(Request $request, User $user, string $googleId): RedirectResponse
    {
        if ($user->google_id !== null && $user->google_id !== $googleId) {
            return $this->oauthError($request, 'Este e-mail já está ligado a outra conta Google.');
        }

        // Conta cujo e-mail nunca foi confirmado pode ter sido aberta por outra pessoa com este endereço:
        // ao confirmar a posse pelo Google, derruba as sessões e os "lembrar-me" que já existiam nela.
        if ($user->email_verified_at === null) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->forceFill(['remember_token' => Str::random(60)]);
        }

        $user->google_id = $googleId;
        $user->email_verified_at ??= now();

        try {
            $user->save();
        } catch (QueryException $exception) {
            if (str_contains(Str::lower($exception->getMessage()), 'users_google_id_unique')) {
                return $this->oauthError($request, 'Esta conta Google já está vinculada a outro usuário.');
            }

            throw $exception;
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function linkAccount(Request $request, string $googleId, string $email): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->oauthError($request, 'Entre na sua conta antes de vincular o Google.');
        }

        $linkedUser = User::where('google_id', $googleId)->first();

        if ($linkedUser && $linkedUser->id !== $user->id) {
            return $this->oauthError($request, 'Esta conta Google já está vinculada a outro usuário.');
        }

        $emailOwner = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($emailOwner && $emailOwner->id !== $user->id) {
            return $this->oauthError(
                $request,
                'O e-mail desta conta Google pertence a outro usuário. Entre nessa conta antes de vincular o Google.'
            );
        }

        $user->google_id = $googleId;

        if (Str::lower($user->email) === $email) {
            $user->email_verified_at ??= now();
        }

        try {
            $user->save();
        } catch (QueryException $exception) {
            if (str_contains(Str::lower($exception->getMessage()), 'users_google_id_unique')) {
                return $this->oauthError($request, 'Esta conta Google já está vinculada a outro usuário.');
            }

            throw $exception;
        }
        $request->session()->regenerate();

        return Redirect::route('profile.edit')->with('status', 'Conta Google vinculada com sucesso.');
    }

    private function oauthError(Request $request, string $message): RedirectResponse
    {
        return Redirect::route($request->user() ? 'profile.edit' : 'login')->with('status', $message);
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
