<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailCodeMail extends Mailable
{
    public function __construct(public readonly string $code, public readonly int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Seu código da Estação Commit: {$this->code}");
    }

    public function content(): Content
    {
        $base = rtrim((string) config('app.url'), '/');

        return new Content(
            view: 'emails.code',
            text: 'emails.code-text',
            with: [
                'code' => $this->code,
                'minutes' => $this->minutes,
                // Clientes de e-mail não carregam WebP/SVG de forma confiável: PNG com URL absoluta.
                'logo' => $base.'/images/estacao-commit/mascots/foguete-gato.png',
                'url' => $base,
                'host' => parse_url($base, PHP_URL_HOST) ?: $base,
            ],
        );
    }
}
