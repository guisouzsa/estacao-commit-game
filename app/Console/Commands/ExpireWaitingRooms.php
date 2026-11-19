<?php

namespace App\Console\Commands;

use App\Services\RoomExpirationService;
use Illuminate\Console\Command;

class ExpireWaitingRooms extends Command
{
    protected $signature = 'rooms:expire-waiting';

    protected $description = 'Expira salas sem participantes adicionais após cinco minutos';

    public function handle(RoomExpirationService $expiration): int
    {
        $expiredCount = $expiration->expireDueRooms();

        $this->info("Salas expiradas: {$expiredCount}");

        return self::SUCCESS;
    }
}
