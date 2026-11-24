<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmailCodeController extends Controller
{
    public function __construct(private readonly EmailVerificationCode $codes) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! EmailVerificationCode::isPending($user)) {
            return redirect()->route('dashboard');
        }

        [$local, $domain] = explode('@', $user->email, 2) + [1 => ''];

        return Inertia::render('Auth/VerifyEmailCode', [
            'maskedEmail' => substr($local, 0, 2).str_repeat('•', max(1, strlen($local) - 2)).'@'.$domain,
            'cooldown' => $this->codes->cooldown($user),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A\d{6}\z/']], [
            'code.regex' => 'Digite os 6 números do código.',
        ]);

        $result = $this->codes->verify($user, $data['code']);
        if ($result !== 'ok') {
            throw ValidationException::withMessages(['code' => match ($result) {
                'expired' => 'Este código expirou. Peça um novo.',
                'locked' => 'Muitas tentativas. Peça um novo código.',
                default => 'Código incorreto.',
            }]);
        }

        return redirect()->route('dashboard');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! EmailVerificationCode::isPending($user)) {
            return redirect()->route('dashboard');
        }
        if ($this->codes->cooldown($user) > 0) {
            throw ValidationException::withMessages(['code' => 'Aguarde um pouco antes de pedir outro código.']);
        }

        $sent = $this->codes->issue($user);

        return back()->with('status', $sent ? 'Enviamos um novo código.' : 'Não conseguimos enviar agora. Tente de novo em instantes.');
    }
}
