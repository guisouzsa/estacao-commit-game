<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerificationCode;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Segunda etapa do cadastro: confirma o código e SÓ ENTÃO cria a conta. Rotas de visitante (guest). */
class EmailCodeController extends Controller
{
    public function __construct(private readonly EmailVerificationCode $codes) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $pending = $this->codes->pending($request);
        if (! $pending) {
            return redirect()->route('register');
        }

        [$local, $domain] = explode('@', $pending['email'], 2) + [1 => ''];

        return Inertia::render('Auth/VerifyEmailCode', [
            'maskedEmail' => substr($local, 0, 2).str_repeat('•', max(1, strlen($local) - 2)).'@'.$domain,
            'cooldown' => $this->codes->cooldown($pending),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A\d{6}\z/']], [
            'code.regex' => 'Digite os 6 números do código.',
        ]);

        $result = $this->codes->verify($request, $data['code']);
        if ($result === 'none') {
            return redirect()->route('register');
        }
        if ($result !== 'ok') {
            throw ValidationException::withMessages(['code' => match ($result) {
                'expired' => 'Este código expirou. Peça um novo.',
                'locked' => 'Muitas tentativas. Peça um novo código.',
                default => 'Código incorreto.',
            }]);
        }

        $pending = $this->codes->pending($request);
        $user = $this->createAccount($pending);
        $this->codes->forget($request);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->codes->pending($request);
        if (! $pending) {
            return redirect()->route('register');
        }
        if ($this->codes->cooldown($pending) > 0) {
            throw ValidationException::withMessages(['code' => 'Aguarde um pouco antes de pedir outro código.']);
        }

        $sent = $this->codes->issue($request);

        return back()->with('status', $sent ? 'Enviamos um novo código.' : 'Não conseguimos enviar agora. Tente de novo em instantes.');
    }

    /** Cria a conta já verificada. Nome ou e-mail tomados nesse meio-tempo voltam ao cadastro com o aviso. */
    private function createAccount(array $pending): User
    {
        try {
            $user = new User([
                'username' => strtolower($pending['username']),
                'display_name' => strtolower($pending['username']),
                'email' => $pending['email'],
                'password' => $pending['password_hash'], // já é hash; o cast "hashed" não refaz
                'password_set' => true,
            ]);
            $user->email_verified_at = now();
            $user->save();

            return $user;
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            $this->codes->forget(request());

            if (str_contains($message, 'users_username_lower_unique')) {
                throw ValidationException::withMessages(['username' => 'Este nome de usuário já está sendo utilizado. Escolha outro.'])->redirectTo(route('register'));
            }
            if (str_contains($message, 'users_email_lower_unique') || str_contains($message, 'users_email_unique')) {
                throw ValidationException::withMessages(['email' => 'Este e-mail já está cadastrado.'])->redirectTo(route('register'));
            }
            throw $exception;
        }
    }
}
