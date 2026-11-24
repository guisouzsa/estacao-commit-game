<?php

namespace App\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Envio por API HTTPS (Brevo). O Render Free bloqueia SMTP, e esta rota não precisa de domínio
 * próprio: basta um remetente verificado na conta Brevo.
 *
 * Falhas TRANSITÓRIAS (rede, 429, 5xx) viram TransportException: o mailer "resilient" tenta o
 * próximo provedor. Falhas PERMANENTES (chave errada, remetente não verificado, endereço
 * inválido: 4xx) viram RuntimeException comum, que NÃO aciona o provedor alternativo nem repete.
 */
class BrevoTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey, private readonly int $timeout = 8)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        /** @var Email $email */
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom()[0];

        try {
            $response = Http::withHeaders(['api-key' => $this->apiKey, 'accept' => 'application/json'])
                ->timeout($this->timeout)
                ->post('https://api.brevo.com/v3/smtp/email', array_filter([
                    'sender' => ['email' => $from->getAddress(), 'name' => $from->getName() ?: null],
                    'to' => array_map(fn ($a) => ['email' => $a->getAddress()], $email->getTo()),
                    'subject' => $email->getSubject(),
                    'htmlContent' => $email->getHtmlBody(),
                    'textContent' => $email->getTextBody(),
                ]));
        } catch (ConnectionException $e) {
            Log::warning('mail.provider_failed', ['provider' => 'brevo', 'kind' => 'transient', 'reason' => 'connection']);
            throw new TransportException('Brevo indisponível (conexão).');
        }

        if ($response->failed()) {
            $status = $response->status();
            $transient = $status === 429 || $status >= 500;
            Log::warning('mail.provider_failed', ['provider' => 'brevo', 'kind' => $transient ? 'transient' : 'permanent', 'status' => $status]);

            // Nunca inclui a chave nem o corpo do e-mail na mensagem.
            throw $transient
                ? new TransportException("Brevo indisponível (HTTP {$status}).")
                : new \RuntimeException("Brevo recusou o envio (HTTP {$status}).");
        }

        Log::info('mail.sent', ['provider' => 'brevo', 'message_id' => $response->json('messageId')]);
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
