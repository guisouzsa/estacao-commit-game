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
 * - Visitas a páginas (GET): página `Error` do Inertia, com texto adequado a cada código.
 * - Ações (POST/DELETE...) feitas pelo Inertia: volta à tela anterior com um aviso
 *   (toast) em português, sem trocar a tela por uma página de erro.
 * - Nada técnico chega ao navegador: sem stack trace, caminho, SQL ou segredo. Os
 *   detalhes ficam nos logs, ligados à pessoa pelo CÓDIGO DE SUPORTE (request id).
 * - 404 para sala inexistente e para sala de outra pessoa são IGUAIS: não dá para
 *   descobrir se uma sala privada existe.
 *
 * Limite: só cobre respostas que passaram pelo Laravel. Se o Render ou o Apache falham
 * antes de entregar a requisição, a página vem de quem falhou (ver public/errors).
 */
class ErrorResponses
{
    /** Status que ganham tratamento visual. */
    private const HANDLED = [400, 401, 403, 404, 405, 408, 409, 410, 419, 422, 429, 500, 502, 503, 504, 505];

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
        if ($status >= 500 && config('app.debug')) {
            return $response;
        }

        // Falha inesperada ao entrar ou se cadastrar (banco, provedor, bug...): ninguém fica parado sem
        // caminho. Oferece um perfil temporário para jogar agora; a conta pode ser criada depois.
        if ($status >= 500 && self::isAuthFlow($request) && ! self::isPlaying($request)) {
            if (! $request->isMethod('GET')) {
                return redirect()->route($request->is('login') ? 'login' : 'register')
                    ->with('auth_fallback', true)
                    ->with('toast', [
                        'id' => (string) Str::uuid(),
                        'tone' => 'error',
                        'title' => 'Não conseguimos concluir agora',
                        'message' => 'Tente de novo em instantes ou jogue já com um perfil temporário.',
                    ]);
            }

            return Inertia::render('Error', [
                'status' => $status,
                'kind' => 'auth_unavailable',
                'canDashboard' => false,
                'message' => null,
                'requestId' => $request->attributes->get('request_id'),
            ])->toResponse($request)->setStatusCode($status);
        }

        // Ação do Inertia (não é uma visita de página): aviso e volta.
        if ($request->header('X-Inertia') && ! $request->isMethod('GET')) {
            return back(fallback: route('dashboard'))->with('toast', [
                'id' => (string) Str::uuid(),
                'tone' => 'error',
                'title' => $status >= 500 ? 'Algo deu errado do nosso lado' : null,
                'message' => self::customMessage($exception) ?? self::friendly($status),
            ]);
        }

        return Inertia::render('Error', [
            'status' => $status,
            'kind' => self::kind($request, $status),
            'canDashboard' => self::isPlaying($request),
            // Mensagem escrita pelo projeto para este caso (ex.: "Este convite foi revogado.").
            'message' => $status < 500 ? self::customMessage($exception) : null,
            // Código para citar ao suporte: aparece no log do servidor junto da falha.
            'requestId' => $status >= 500 || in_array($status, [408, 429], true)
                ? $request->attributes->get('request_id')
                : null,
        ])->toResponse($request)->setStatusCode($status);
    }

    /** Qual texto mostrar. Sala, convite e perfil têm texto próprio; o resto depende do código. */
    private static function kind(Request $request, int $status): string
    {
        // Convites: o link /convites/CODIGO. 404/410/409 explicam o que houve com o convite.
        if (in_array($status, [404, 409, 410], true) && $request->is('convites/*')) {
            return 'invite';
        }

        return match (true) {
            $status === 404 && $request->is('salas', 'salas/*', 'sala*') => 'room',
            $status === 404 && $request->is('perfil/*', 'u/*') => 'profile',
            $status === 404 => 'page',
            $status === 400 => 'bad_request',
            $status === 401 => 'unauthenticated',
            $status === 403 => 'forbidden',
            $status === 405 => 'method',
            $status === 408 => 'request_timeout',
            $status === 409 => 'conflict',
            $status === 410 => 'gone',
            $status === 419 => 'session',
            $status === 422 => 'invalid_data',
            $status === 429 => 'throttle',
            $status === 502 => 'bad_gateway',
            $status === 503 => 'maintenance',
            $status === 504 => 'gateway_timeout',
            $status === 505 => 'http_version',
            default => 'server',
        };
    }

    private static function friendly(int $status): string
    {
        return match ($status) {
            400 => 'Não conseguimos entender esse pedido. Tente de novo.',
            401 => 'Entre no jogo para continuar.',
            403 => 'Você não tem permissão para realizar essa ação.',
            404 => 'Não encontramos o que você procurava. Pode ter sido removido.',
            405 => 'Essa ação não está disponível desse jeito.',
            408 => 'O pedido demorou demais. Tente novamente.',
            409 => 'Essa ação não pode ser feita agora. Atualize a página e tente novamente.',
            410 => 'Isso não está mais disponível.',
            419 => 'Sua sessão expirou. Atualize a página e tente de novo.',
            422 => 'Confira os dados informados e tente novamente.',
            429 => 'Muitas tentativas seguidas. Aguarde um instante e tente de novo.',
            502, 504 => 'Estamos com dificuldade para falar com o jogo. Tente novamente em instantes.',
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

        // Mensagens do próprio framework (em inglês, com rota/método/classe) nunca chegam ao usuário:
        // só mensagens escritas pelo projeto, em português.
        if ($exception->getStatusCode() === 405 || preg_match('/^(The |No |Call |Undefined|Attempt|Class|Method|Route|Unsupported|SQLSTATE|Invalid |Malformed)/', $message)) {
            return null;
        }

        return $message;
    }

    /** Telas de entrada e cadastro (e-mail, código, Google, recuperação de senha). */
    private static function isAuthFlow(Request $request): bool
    {
        return $request->is(
            'login', 'register', 'verificar-email', 'verificar-email/*',
            'auth/google', 'auth/google/*', 'forgot-password', 'reset-password', 'reset-password/*',
        );
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
