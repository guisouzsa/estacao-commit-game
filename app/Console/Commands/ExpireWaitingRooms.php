<?php

namespace App\Console\Commands;

use App\Services\RoomExpirationService;
use Illuminate\Console\Command;

class ExpireWaitingRooms extends Command
{
    protected $signature = 'rooms:expire-waiting';

    protected $description = 'Exclui salas que ficaram vazias (só o anfitrião) por cinco minutos';

    public function handle(RoomExpirationService $expiration): int
    {
        $expiredCount = $expiration->expireDueRooms();

        $this->info("Salas excluídas: {$expiredCount}");

        return self::SUCCESS;
    }
}
