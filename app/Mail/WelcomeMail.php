<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Boas-vindas, enviado uma única vez quando a conta é criada (por e-mail e senha ou pelo Google). */
class WelcomeMail extends Mailable
{
    public const VIA_EMAIL = 'email';

    public const VIA_GOOGLE = 'google';

    public function __construct(public readonly string $username, public readonly string $via) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Bem-vindo à Estação Commit, @{$this->username}!");
    }

    public function content(): Content
    {
        $base = rtrim((string) config('app.url'), '/');

        return new Content(
            view: 'emails.welcome',
            text: 'emails.welcome-text',
            with: [
                'username' => $this->username,
                'google' => $this->via === self::VIA_GOOGLE,
                // Clientes de e-mail não carregam WebP/SVG de forma confiável: PNG com URL absoluta.
                'logo' => $base.'/images/estacao-commit/mascots/foguete-gato.png',
                'url' => $base,
                'dashboard' => $base.'/dashboard',
                'profile' => $base.'/profile',
                'host' => parse_url($base, PHP_URL_HOST) ?: $base,
            ],
        );
    }
}
