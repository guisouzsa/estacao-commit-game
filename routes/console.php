<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rooms:expire-waiting')->everyMinute();

// Vezes paradas (humano que passou do prazo ou bot sem ninguém olhando). Uma consulta indexada por minuto.
Schedule::command('games:sweep')->everyMinute()->withoutOverlapping(5);

// Salas com partida concluída: exclusão 2 minutos depois da vitória.
Schedule::command('rooms:purge-finished')->everyMinute();

// Perfis de visitante sem atividade por 6 horas (consulta indexada, em lotes).
Schedule::command('guests:prune')->everyFifteenMinutes();
