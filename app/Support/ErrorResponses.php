<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Respostas de erro com a cara do Estação Commit.
 *
 * - Visitas a páginas (GET): página `Error` do Inertia, com texto adequado.
 * - Ações (POST/DELETE...) feitas pelo Inertia: volta à tela anterior com um aviso
 *   (toast) em português, sem trocar a tela por uma página de erro.
 * - Nada técnico chega ao navegador: sem stack trace, caminho, SQL ou segredo.
 *   Os detalhes continuam nos logs do servidor.
 * - 404 para sala inexistente e para sala de outra pessoa são IGUAIS: não dá para
 *   descobrir se uma sala privada existe.
 */
class ErrorResponses
{
    /** Status que ganham tratamento visual. */
    private const HANDLED = [403, 404, 409, 410, 419, 429, 500, 503];

    public static function respond(Response $response, Throwable $exception, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! in_array($status, self::HANDLED, true)) {
            return $response;
        }

        // APIs puras (JSON) mantêm JSON. Pedidos do Inertia não são "JSON puro".
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return $response;
        }

        // Em desenvolvimento, falhas internas mostram a depuração normal.
        if (in_array($status, [500, 503], true) && config('app.debug')) {
            return $response;
        }

        // Ação do Inertia (não é uma visita de página): aviso e volta.
        if ($request->header('X-Inertia') && ! $request->isMethod('GET')) {
            return back(fallback: route('dashboard'))->with('toast', [
                'id' => (string) Str::uuid(),
                'tone' => 'error',
                'message' => self::customMessage($exception) ?? self::friendly($status),
            ]);
        }

        return Inertia::render('Error', [
            'status' => $status,
            'kind' => self::kind($request, $status),
            'canDashboard' => self::isPlaying($request),
        ])->toResponse($request)->setStatusCode($status);
    }

    /** Qual texto mostrar. Sala e perfil têm texto próprio; o resto é genérico. */
    private static function kind(Request $request, int $status): string
    {
        return match (true) {
            $status === 404 && $request->is('salas', 'salas/*', 'sala*', 'convites/*') => 'room',
            $status === 404 && $request->is('perfil/*', 'u/*') => 'profile',
            $status === 404 => 'page',
            $status === 403 => 'forbidden',
            $status === 409 => 'conflict',
            $status === 410 => 'gone',
            $status === 419 => 'session',
            $status === 429 => 'throttle',
            $status === 503 => 'maintenance',
            default => 'server',
        };
    }

    private static function friendly(int $status): string
    {
        return match ($status) {
            403 => 'Você não tem permissão para realizar essa ação.',
            404 => 'Não encontramos o que você procurava. Pode ter sido removido.',
            409 => 'Essa ação não pode ser feita agora. Atualize a página e tente novamente.',
            410 => 'Isso não está mais disponível.',
            419 => 'Sua sessão expirou. Atualize a página e tente de novo.',
            429 => 'Muitas tentativas seguidas. Aguarde um instante e tente de novo.',
            503 => 'O serviço está temporariamente indisponível. Tente novamente em instantes.',
            default => 'Algo deu errado do nosso lado. Tente novamente em instantes.',
        };
    }

    /**
     * Mensagem escrita pelo próprio projeto (em português) em um abort(): segura
     * para exibir. Mensagens padrão do framework (em inglês) e técnicas, como
     * "No query results for model [...]", nunca passam.
     */
    private static function customMessage(Throwable $exception): ?string
    {
        if (! $exception instanceof HttpExceptionInterface) {
            return null;
        }

        $message = trim($exception->getMessage());
        $defaults = [
            Response::$statusTexts[$exception->getStatusCode()] ?? '',
            'This action is unauthorized.',
            'CSRF token mismatch.',
            'Unauthenticated.',
            'Server Error',
            'Page Expired',
        ];

        if ($message === '' || in_array($message, $defaults, true)) {
            return null;
        }

        if (str_contains($message, '\\') || str_contains($message, '[') || str_contains($message, 'No query results')) {
            return null;
        }

        return $message;
    }

    /** Há alguém jogando (conta ou visitante)? Define se oferecemos a Dashboard. */
    private static function isPlaying(Request $request): bool
    {
        try {
            return $request->user() !== null || Auth::guard('visitor')->check();
        } catch (Throwable) {
            // Se até a autenticação falhou (ex.: banco fora do ar), não insiste.
            return false;
        }
    }
}
