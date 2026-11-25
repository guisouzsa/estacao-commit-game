<?php

namespace App\Console\Commands;

use App\Services\GuestPruner;
use Illuminate\Console\Command;

class PruneGuestProfiles extends Command
{
    protected $signature = 'guests:prune';

    protected $description = 'Remove perfis temporários de visitante sem atividade (nunca quem está em partida ativa)';

    public function handle(GuestPruner $pruner): int
    {
        $this->info("Perfis temporários removidos: {$pruner->prune()}");

        return self::SUCCESS;
    }
}
