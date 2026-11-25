<?php

namespace App\Support;

use Throwable;

/**
 * Executa uma rotina agendada DENTRO do processo do `schedule:run` (sem subprocesso PHP por tarefa) e registra o resultado
 * de forma econômica no log operacional:
 *
 *  - uma linha `cron.job` só quando a rotina PROCESSOU algo, falhou ou foi lenta (> SLOW_MS). Rodar "sem nada a fazer"
 *    não gera log: são ~1.440 execuções por dia por tarefa;
 *  - falha de uma rotina é registrada (`cron.job_failed`) e NÃO impede as demais nem a próxima execução (tudo é idempotente);
 *  - o log leva o nome da rotina, quantos registros foram processados, a duração e o pico de memória. Nunca dados pessoais.
 *
 * A rotina devolve um inteiro (quantos registros tratou) ou um array de contadores (ex.: ['timeouts' => 1, 'bot_steps' => 3]).
 */
final class ScheduledJob
{
    /** Acima disto a execução é registrada mesmo sem trabalho (sinal de banco lento ou do Neon acordando). */
    public const SLOW_MS = 2000;

    /** @param callable(): (int|array<string, int>) $job */
    public static function run(string $name, callable $job): void
    {
        $started = hrtime(true);

        try {
            $result = $job();
        } catch (Throwable $e) {
            OpsLog::event('cron.job_failed', [
                'job' => $name,
                'duration_ms' => self::elapsedMs($started),
                'error' => class_basename($e),
                'message' => mb_substr($e->getMessage(), 0, 160),
            ], 'error');

            return;
        }

        $counters = is_array($result) ? $result : ['processed' => (int) $result];
        $ms = self::elapsedMs($started);
        $worked = array_sum(array_map('intval', array_diff_key($counters, ['checked' => 0]))) > 0;

        if ($worked || $ms > self::SLOW_MS) {
            OpsLog::event('cron.job', [
                'job' => $name,
                ...$counters,
                'duration_ms' => $ms,
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ]);
        }
    }

    /** Sinal de vida a cada 15 minutos: prova que o agendador está rodando sem poluir o log a cada minuto. */
    public static function heartbeat(): void
    {
        OpsLog::event('cron.heartbeat', [
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);
    }

    private static function elapsedMs(int $startedNs): int
    {
        return (int) round((hrtime(true) - $startedNs) / 1e6);
    }
}
