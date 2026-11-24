<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de defesa em profundidade em TODAS as respostas do Laravel:
 * - X-Content-Type-Options: o navegador não "adivinha" o tipo (evita executar upload disfarçado).
 * - X-Frame-Options: o site não pode ser embutido em iframe de outro domínio (clickjacking).
 * - Referrer-Policy: não vaza URLs internas (salas, convites) para sites externos.
 * - Permissions-Policy: o jogo não precisa de câmera, microfone nem localização.
 * - HSTS: só sob HTTPS (o Render termina o TLS; trustProxies informa o esquema real).
 * Os arquivos estáticos (build, imagens) recebem os mesmos cabeçalhos no Apache (docker/apache/perf-headers.conf).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
