<?php

namespace App\Console\Commands;

use App\Services\GameInactivityService;
use Illuminate\Console\Command;

class SweepStalledGames extends Command
{
    protected $signature = 'games:sweep';

    protected $description = 'Resolve vezes paradas (timeout de humanos e bots) sem depender de alguém abrir a partida';

    public function handle(GameInactivityService $service): int
    {
        $result = $service->sweepStalled();
        $this->info("Partidas verificadas: {$result['checked']} · timeouts: {$result['timeouts']} · passos de bot: {$result['bot_steps']}");

        return self::SUCCESS;
    }
}
