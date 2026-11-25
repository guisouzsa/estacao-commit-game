<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Log operacional padronizado: UM nome de evento ("categoria.acao") e contexto sem segredos.
 *
 * O `request_id` (código de suporte) já entra em todo log da requisição por Log::shareContext
 * (ver Http/Middleware/AssignRequestId). Aqui acrescentamos o evento, o ambiente e o resultado.
 * Campos com nome sensível (senha, token, código, segredo, cookie...) são trocados por "[oculto]".
 *
 * Eventos em uso (ver docs/ARQUITETURA.md, seção de logs):
 *   auth.login, auth.login_failed, auth.register, auth.google, auth.password_reset, auth.email_verification,
 *   mail.sent, mail.provider_failed, game.created, game.turn_timeout, game.player_inactive,
 *   game.host_transferred, game.bot_stalled, game.finished, integration.failure
 */
final class OpsLog
{
    private const SENSITIVE = '/pass|senha|token|secret|segredo|code|codigo|cookie|api[_-]?key|authorization/i';

    /** @param array<string, mixed> $context */
    public static function event(string $event, array $context = [], string $level = 'info'): void
    {
        Log::log($level, $event, [
            'event' => $event,
            'env' => config('app.env'),
            ...self::redact($context),
        ]);
    }

    /** Esconde "chave=valor" de segredos dentro de textos livres (mensagens de exceção de bibliotecas HTTP). */
    public static function scrub(string $text): string
    {
        $pattern = "/((?:client_secret|api[_-]?key|secret|token|password|authorization)[\"']?\\s*[=:]\\s*[\"']?)[^\\s&\"',}]+/i";

        return preg_replace($pattern, '$1[oculto]', $text) ?? $text;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function redact(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE, $key) === 1) {
                $clean[$key] = '[oculto]';
            } elseif (is_array($value)) {
                $clean[$key] = self::redact($value);
            } elseif (is_string($value)) {
                $clean[$key] = self::scrub($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
