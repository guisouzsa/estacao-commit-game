<?php

use App\Services\FinishedRoomCleanup;
use App\Services\GameInactivityService;
use App\Services\GuestPruner;
use App\Services\RoomExpirationService;
use App\Support\ScheduledJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Rotinas agendadas
|--------------------------------------------------------------------------
|
| Quem dispara: docker/supervisor/scheduler-loop.sh executa `php artisan schedule:run` UMA vez por minuto
| (processo curto, sem PHP residente). As rotinas abaixo rodam DENTRO desse mesmo processo (Schedule::call), em vez de
| um subprocesso PHP por tarefa: 1 inicialização do Laravel por minuto em vez de 4. Cada uma passa por ScheduledJob,
| que registra só o que importa (trabalho feito, falha ou lentidão) e isola falhas entre as tarefas.
|
| Os comandos artisan (rooms:expire-waiting, games:sweep, ...) continuam existindo para execução manual e usam
| os mesmos serviços. Documentação: docs/DEPLOY/CRON-JOBS.md.
|
*/

// Salas em espera que ficaram vazias (só o anfitrião) por 5 minutos. Idempotente.
Schedule::call(fn () => ScheduledJob::run('rooms:expire-waiting', fn () => app(RoomExpirationService::class)->expireDueRooms()))
    ->name('rooms:expire-waiting')->everyMinute();

// Vezes paradas (humano que passou do prazo ou bot sem ninguém olhando). Uma consulta indexada por minuto, em lote.
// withoutOverlapping: durante um deploy duas instâncias podem coexistir por instantes; só uma trata as partidas.
Schedule::call(fn () => ScheduledJob::run('games:sweep', fn () => app(GameInactivityService::class)->sweepStalled()))
    ->name('games:sweep')->everyMinute()->withoutOverlapping(5);

// Salas com partida concluída: exclusão 2 minutos depois da vitória. Idempotente.
Schedule::call(fn () => ScheduledJob::run('rooms:purge-finished', fn () => app(FinishedRoomCleanup::class)->purgeDue()))
    ->name('rooms:purge-finished')->everyMinute();

// Perfis de visitante sem atividade (6 h; teto de 3 dias). Consulta indexada, em lotes de 200.
Schedule::call(fn () => ScheduledJob::run('guests:prune', fn () => app(GuestPruner::class)->prune()))
    ->name('guests:prune')->everyFifteenMinutes();

// Sinal de vida: uma linha a cada 15 minutos mostra nos logs que o agendador está rodando.
Schedule::call(fn () => ScheduledJob::heartbeat())->name('cron:heartbeat')->everyFifteenMinutes();
