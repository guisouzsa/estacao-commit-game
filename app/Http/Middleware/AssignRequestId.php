<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dá a cada requisição um código curto ("código de suporte").
 *
 * O código entra no contexto de TODOS os logs da requisição (inclusive o da exceção, com
 * stack trace) e volta no cabeçalho X-Request-Id. A página de erro mostra o código: quem
 * cita "F3K9Q2XA" ao suporte permite achar a falha exata no log, sem expor nada técnico.
 *
 * Sempre gerado aqui: nunca aceita um id vindo do cliente (evita forjar/poluir logs).
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = Str::upper(Str::random(8));

        $request->attributes->set('request_id', $id);
        Log::shareContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
