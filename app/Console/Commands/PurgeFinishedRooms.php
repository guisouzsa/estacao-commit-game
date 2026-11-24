<?php

namespace App\Console\Commands;

use App\Services\FinishedRoomCleanup;
use Illuminate\Console\Command;

class PurgeFinishedRooms extends Command
{
    protected $signature = 'rooms:purge-finished';

    protected $description = 'Exclui salas cuja partida terminou há mais de 2 minutos (o histórico de resultados é preservado)';

    public function handle(FinishedRoomCleanup $cleanup): int
    {
        $this->info('Salas excluídas: '.$cleanup->purgeDue());

        return self::SUCCESS;
    }
}
