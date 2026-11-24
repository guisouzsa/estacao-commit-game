<?php

namespace App\Services;

use App\Mail\EmailCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Cadastro em duas etapas: os dados ficam SÓ na sessão do navegador até o código ser confirmado.
 * Nenhuma conta é criada antes disso, então o login continua sendo apenas e-mail e senha.
 * O código é guardado como hash, com validade, limite de tentativas e intervalo entre reenvios.
 */
class EmailVerificationCode
{
    private const KEY = 'registration.pending';

    public static function enabled(): bool
    {
        return (bool) config('verification.enabled');
    }

    public function pending(Request $request): ?array
    {
        return $request->session()->get(self::KEY);
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }

    /** Guarda o cadastro (senha já em hash) e envia o primeiro código. */
    public function start(Request $request, string $username, string $email, string $password): bool
    {
        $request->session()->put(self::KEY, [
            'username' => $username,
            'email' => $email,
            'password_hash' => Hash::make($password),
        ]);

        return $this->issue($request);
    }

    /** Segundos até poder reenviar (0 = já pode). */
    public function cooldown(array $pending): int
    {
        return max(0, (int) config('verification.resend_cooldown_seconds') - (now()->getTimestamp() - (int) ($pending['sent_at'] ?? 0)));
    }

    /** Gera um código novo e envia. Falha de envio não derruba o chamador (retorna false). */
    public function issue(Request $request): bool
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = (int) config('verification.ttl_minutes');

        $request->session()->put(self::KEY, [
            ...$pending,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($minutes)->getTimestamp(),
            'sent_at' => now()->getTimestamp(),
            'attempts' => 0,
        ]);

        try {
            Mail::to($pending['email'])->send(new EmailCodeMail($code, $minutes));
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    /** @return 'ok'|'invalid'|'expired'|'locked'|'none' */
    public function verify(Request $request, string $code): string
    {
        $pending = $this->pending($request);
        if (! $pending || ! isset($pending['code_hash'])) {
            return 'none';
        }
        if ((int) ($pending['attempts'] ?? 0) >= (int) config('verification.max_attempts')) {
            return 'locked';
        }
        if (now()->getTimestamp() > (int) ($pending['expires_at'] ?? 0)) {
            return 'expired';
        }
        if (! Hash::check($code, $pending['code_hash'])) {
            $request->session()->put(self::KEY, [...$pending, 'attempts' => (int) ($pending['attempts'] ?? 0) + 1]);

            return 'invalid';
        }

        return 'ok';
    }
}
