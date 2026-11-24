<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\Middleware\AuthenticateSession;

/**
 * AuthenticateSession só faz sentido para o guard de SESSÃO (contas). O guard "visitor"
 * identifica o visitante pelo cookie próprio (RequestGuard, sem "lembrar-me" nem senha),
 * então ele passa direto, sem checagem de hash de senha.
 */
class AuthenticateAccountSession extends AuthenticateSession
{
    public function handle($request, Closure $next)
    {
        if (! method_exists($this->auth->guard(), 'viaRemember')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
