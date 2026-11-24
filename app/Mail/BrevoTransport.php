<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Envio por API HTTPS (Brevo). O Render Free bloqueia SMTP, e esta rota não precisa de domínio
 * próprio: basta um remetente verificado na conta Brevo.
 */
class BrevoTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        /** @var Email $email */
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom()[0];

        $response = Http::withHeaders(['api-key' => $this->apiKey, 'accept' => 'application/json'])
            ->timeout(8)
            ->post('https://api.brevo.com/v3/smtp/email', array_filter([
                'sender' => ['email' => $from->getAddress(), 'name' => $from->getName() ?: null],
                'to' => array_map(fn ($a) => ['email' => $a->getAddress()], $email->getTo()),
                'subject' => $email->getSubject(),
                'htmlContent' => $email->getHtmlBody(),
                'textContent' => $email->getTextBody(),
            ]));

        if ($response->failed()) {
            // Nunca inclui a chave nem o corpo do e-mail na mensagem.
            throw new TransportException('Brevo recusou o envio (HTTP '.$response->status().').');
        }
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
