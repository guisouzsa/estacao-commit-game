<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso de segurança: uma conta Google passou a dar acesso a esta conta. */
class GoogleLinkedMail extends Mailable
{
    /** Pelo botão "Vincular Google" do perfil. */
    public const HOW_SETTINGS = 'settings';

    /** Entrando com o Google numa conta que já usava o mesmo e-mail. */
    public const HOW_SIGN_IN = 'sign_in';

    public function __construct(public readonly string $username, public readonly string $how) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sua conta Google foi vinculada à Estação Commit');
    }

    public function content(): Content
    {
        $base = rtrim((string) config('app.url'), '/');

        return new Content(
            view: 'emails.google-linked',
            text: 'emails.google-linked-text',
            with: [
                'username' => $this->username,
                'fromSettings' => $this->how === self::HOW_SETTINGS,
                'logo' => $base.'/images/estacao-commit/mascots/foguete-gato.png',
                'url' => $base,
                'profile' => $base.'/profile',
                'host' => parse_url($base, PHP_URL_HOST) ?: $base,
            ],
        );
    }
}
