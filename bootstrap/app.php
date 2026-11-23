<?php

use App\Support\ErrorResponses;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Código de suporte em toda requisição (logs e página de erro).
        $middleware->prepend(\App\Http\Middleware\AssignRequestId::class);

        // Rotas do jogo (Dashboard, salas, partidas) não mandam ninguém para o login: levam à criação
        // de um perfil temporário, e a URL pedida é lembrada para voltar depois. Rotas de conta
        // (perfil, rankings, notificações) continuam exigindo login.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('dashboard', 'rooms.*', 'games.*')
            ? route('guest.create')
            : route('login'));

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Callback do Worker da Cloudflare: autenticado por assinatura HMAC, sem sessão/CSRF.
        $middleware->validateCsrfTokens(except: ['realtime/callback']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Páginas de erro com a identidade do jogo, sem expor detalhes técnicos.
        $exceptions->respond(fn (Response $response, Throwable $exception, Request $request) => ErrorResponses::respond($response, $exception, $request));
    })->create();
