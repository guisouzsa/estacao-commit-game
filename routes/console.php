<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rooms:expire-waiting')->everyMinute();

// Perfis de visitante sem atividade (consulta indexada, em lotes).
Schedule::command('guests:prune')->hourly();
