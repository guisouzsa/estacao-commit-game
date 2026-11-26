<?php

namespace App\Support;

/**
 * Houve gente de verdade usando o site há pouco? Guardado num arquivo (sem banco): o horário do arquivo é a
 * última requisição web. O monitor externo que mantém o Render acordado (cron-job.org batendo em /up) NÃO conta,
 * porque /up fica fora do grupo "web".
 *
 * Serve para as rotinas agendadas não consultarem o Neon a cada minuto quando ninguém está jogando: assim o banco
 * pode pausar e não gasta a cota grátis. As rotinas são idempotentes e tudo o que ficar pendente é resolvido
 * quando alguém voltar (ou na rodada de manutenção de hora em hora).
 */
class SiteActivity
{
    private static function path(): string
    {
        return storage_path('framework/last-activity');
    }

    /** Marca atividade. Grava no máximo uma vez por minuto (barato mesmo com muitas requisições). */
    public static function touch(): void
    {
        $path = self::path();
        clearstatcache(true, $path);
        $modified = @filemtime($path);

        if ($modified === false || time() - $modified >= 60) {
            @touch($path);
        }
    }

    public static function recent(int $minutes): bool
    {
        $path = self::path();
        clearstatcache(true, $path);
        $modified = @filemtime($path);

        return $modified !== false && time() - $modified <= $minutes * 60;
    }

    /**
     * As rotinas com banco rodam se houve uso nos últimos `game.scheduler_idle_minutes` minutos, ou na rodada de
     * manutenção (minuto 0 de cada hora). Com o valor 0, a trava fica desligada e tudo roda sempre.
     */
    public static function shouldRunRoutines(): bool
    {
        $minutes = (int) config('game.scheduler_idle_minutes');

        return $minutes <= 0 || self::recent($minutes) || (int) now()->format('i') === 0;
    }
}
