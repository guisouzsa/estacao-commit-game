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
        $html = '<div style="font-family:Arial,sans-serif;max-width:420px">'
            .'<h2>Estação Commit</h2><p>Use este código para confirmar seu e-mail:</p>'
            .'<p style="font-size:32px;font-weight:bold;letter-spacing:6px">'.e($this->code).'</p>'
            .'<p>Ele vale por '.$this->minutes.' minutos. Se não foi você, ignore esta mensagem.</p></div>';

        return new Content(htmlString: $html);
    }
}
