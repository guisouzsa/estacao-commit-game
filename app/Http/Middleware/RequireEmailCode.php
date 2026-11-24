<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\EmailVerificationCode;
use Closure;
use Illuminate\Http\Request;

/** Conta com código pendente só alcança a tela do código e o "sair". Visitantes e contas antigas passam. */
class RequireEmailCode
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user(); // guard web (conta)
        if ($user instanceof User && EmailVerificationCode::isPending($user) && ! $request->routeIs('verification.*', 'logout')) {
            return redirect()->route('verification.code');
        }

        return $next($request);
    }
}
