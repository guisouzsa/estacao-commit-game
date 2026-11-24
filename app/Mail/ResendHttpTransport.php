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
 * Provedor ALTERNATIVO (Resend) por API HTTPS, sem dependência extra. Mesma regra do Brevo:
 * falha transitória -> TransportException (permite tentar outro provedor); permanente -> RuntimeException.
 * Atenção: no plano gratuito do Resend, sem domínio verificado, só é possível enviar ao dono da conta.
 */
class ResendHttpTransport extends AbstractTransport
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
            $response = Http::withToken($this->apiKey)->timeout($this->timeout)->post('https://api.resend.com/emails', array_filter([
                'from' => $from->getName() ? "{$from->getName()} <{$from->getAddress()}>" : $from->getAddress(),
                'to' => array_map(fn ($a) => $a->getAddress(), $email->getTo()),
                'subject' => $email->getSubject(),
                'html' => $email->getHtmlBody(),
                'text' => $email->getTextBody(),
            ]));
        } catch (ConnectionException $e) {
            Log::warning('mail.provider_failed', ['provider' => 'resend', 'kind' => 'transient', 'reason' => 'connection']);
            throw new TransportException('Resend indisponível (conexão).');
        }

        if ($response->failed()) {
            $status = $response->status();
            $transient = $status === 429 || $status >= 500;
            Log::warning('mail.provider_failed', ['provider' => 'resend', 'kind' => $transient ? 'transient' : 'permanent', 'status' => $status]);

            throw $transient
                ? new TransportException("Resend indisponível (HTTP {$status}).")
                : new \RuntimeException("Resend recusou o envio (HTTP {$status}).");
        }

        Log::info('mail.sent', ['provider' => 'resend', 'message_id' => $response->json('id')]);
    }

    public function __toString(): string
    {
        return 'resend-http';
    }
}
