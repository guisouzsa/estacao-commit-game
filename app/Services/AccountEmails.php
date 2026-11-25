<?php

namespace App\Services;

use App\Mail\GoogleLinkedMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Support\OpsLog;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Avisos de conta que são cortesia (boas-vindas e "Google vinculado"). Regras:
 * - UMA tentativa, no provedor principal (Brevo). Se ele der erro (fora do ar, cota esgotada,
 *   429...), o aviso simplesmente não é enviado: sem repetir e sem usar o provedor reserva, que
 *   fica guardado para o que é essencial (código de verificação e recuperação de senha).
 * - Nunca derruba o cadastro/login: qualquer falha vira só um registro no log operacional,
 *   sem o endereço nem o conteúdo do e-mail.
 */
class AccountEmails
{
    public function welcome(User $user): bool
    {
        $via = filled($user->google_id) && ! $user->password_set ? WelcomeMail::VIA_GOOGLE : WelcomeMail::VIA_EMAIL;

        return $this->sendOnce($user, new WelcomeMail($user->username, $via), 'welcome', ['via' => $via]);
    }

    /** Conta Google ligada a uma conta que já existia (pelo botão do perfil ou entrando com Google). */
    public function googleLinked(User $user, string $how): bool
    {
        return $this->sendOnce($user, new GoogleLinkedMail($user->username, $how), 'google_linked', ['how' => $how]);
    }

    /** @param  array<string, string>  $context */
    private function sendOnce(User $user, Mailable $mail, string $kind, array $context): bool
    {
        if (! config('verification.welcome_enabled') || blank($user->email)) {
            return false;
        }

        try {
            Mail::mailer($this->primaryMailer())->to($user->email)->send($mail);
            OpsLog::event('mail.'.$kind, ['user' => $user->getKey(), ...$context, 'result' => 'sent']);

            return true;
        } catch (\Throwable $e) {
            OpsLog::event('mail.'.$kind, [
                'user' => $user->getKey(),
                ...$context,
                'result' => 'skipped',
                'error' => class_basename($e),
                'message' => Str::limit($e->getMessage(), 160),
            ], 'warning');

            return false;
        }
    }

    /** O mailer "resilient" é uma cadeia (Brevo -> Resend): aqui só vale o primeiro elo. */
    private function primaryMailer(): string
    {
        $default = (string) config('mail.default');
        $chain = config("mail.mailers.{$default}.transport") === 'failover'
            ? (array) config("mail.mailers.{$default}.mailers")
            : [];

        return $chain[0] ?? $default;
    }
}
