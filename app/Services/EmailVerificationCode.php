<?php

namespace App\Services;

use App\Mail\EmailCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/** Código de 6 dígitos guardado só como hash, com validade, limite de tentativas e reenvio. */
class EmailVerificationCode
{
    public static function enabled(): bool
    {
        return (bool) config('verification.enabled');
    }

    public static function isPending(User $user): bool
    {
        return self::enabled() && $user->email_code_hash !== null && $user->email_verified_at === null;
    }

    /** Segundos até poder reenviar (0 = já pode). */
    public function cooldown(User $user): int
    {
        if (! $user->email_code_sent_at) {
            return 0;
        }

        return max(0, (int) config('verification.resend_cooldown_seconds') - (int) $user->email_code_sent_at->diffInSeconds(now(), true));
    }

    /** Gera um código novo e envia. Falha de envio não derruba o chamador (retorna false). */
    public function issue(User $user): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = (int) config('verification.ttl_minutes');

        $user->forceFill([
            'email_code_hash' => Hash::make($code),
            'email_code_expires_at' => now()->addMinutes($minutes),
            'email_code_sent_at' => now(),
            'email_code_attempts' => 0,
        ])->save();

        try {
            Mail::to($user->email)->send(new EmailCodeMail($code, $minutes));
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    /** @return 'ok'|'invalid'|'expired'|'locked' */
    public function verify(User $user, string $code): string
    {
        if ($user->email_code_hash === null) {
            return 'invalid';
        }
        if ($user->email_code_attempts >= (int) config('verification.max_attempts')) {
            return 'locked';
        }
        if (! $user->email_code_expires_at || $user->email_code_expires_at->isPast()) {
            return 'expired';
        }
        if (! Hash::check($code, $user->email_code_hash)) {
            $user->forceFill(['email_code_attempts' => $user->email_code_attempts + 1])->save();

            return 'invalid';
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_code_hash' => null,
            'email_code_expires_at' => null,
            'email_code_sent_at' => null,
            'email_code_attempts' => 0,
        ])->save();

        return 'ok';
    }
}
