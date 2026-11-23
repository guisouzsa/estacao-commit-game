<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Ponte Laravel -> Worker da Cloudflare. O Laravel continua dono do estado e da
 * identidade; o Worker só espalha o aviso "algo mudou" para quem está conectado.
 */
class CloudflareRealtime
{
    public static function enabled(): bool
    {
        return config('realtime.driver') === 'cloudflare'
            && config('realtime.worker_url') !== ''
            && filled(config('realtime.notify_secret'))
            && filled(config('realtime.token_secret'));
    }

    /** Token curto (uso único) que autoriza UM ator a abrir UM canal no Worker. */
    public function connectionToken(string $channel, string $subject): string
    {
        $claims = [
            'ch' => $channel,
            'sub' => $subject,
            'exp' => time() + (int) config('realtime.token_ttl_seconds'),
            'jti' => (string) Str::uuid(),
        ];
        $body = self::b64url(json_encode($claims, JSON_UNESCAPED_SLASHES));

        return $body.'.'.self::b64url(hash_hmac('sha256', $body, (string) config('realtime.token_secret'), true));
    }

    public function socketUrl(string $channel): string
    {
        $base = preg_replace('#^http#', 'ws', (string) config('realtime.worker_url'));

        return $base.'/ws/'.rawurlencode($channel);
    }

    /** @param  array<string, mixed>  $data */
    public function notify(string $channel, string $event, array $data): void
    {
        $body = json_encode(['event' => $event, 'data' => $data], JSON_UNESCAPED_SLASHES);

        Http::timeout(3)->withBody($body, 'application/json')->withHeaders($this->signedHeaders($body))
            ->post(config('realtime.worker_url').'/notify/'.rawurlencode($channel))->throw();
    }

    /** Cabeçalhos de uma chamada Laravel -> Worker: HMAC de "timestamp.nonce.sha256(corpo)". */
    public function signedHeaders(string $body): array
    {
        $timestamp = (string) time();
        $nonce = Str::random(24);

        return [
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => hash_hmac('sha256', "$timestamp.$nonce.".hash('sha256', $body), (string) config('realtime.notify_secret')),
        ];
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
