<?php

namespace App\Console\Commands;

use App\Models\GuestProfile;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneGuestProfiles extends Command
{
    protected $signature = 'guests:prune';

    protected $description = 'Remove perfis temporários de visitante sem atividade (nunca quem está em partida ativa)';

    public function handle(): int
    {
        $removed = 0;

        // Em lotes: nunca carrega todos os perfis de uma vez.
        GuestProfile::query()
            ->expired()
            ->deletable()
            ->select('id')
            ->chunkById(200, function ($guests) use (&$removed): void {
                $ids = $guests->pluck('id')->all();

                // Avisos pendentes de quem some não têm mais para quem ser entregues.
                DatabaseNotification::query()
                    ->where('notifiable_type', GuestProfile::class)
                    ->whereIn('notifiable_id', $ids)
                    ->delete();

                // Participações e salas ligadas a eles saem por cascata (FKs).
                $removed += GuestProfile::query()->whereIn('id', $ids)->delete();
            });

        $this->info("Perfis temporários removidos: {$removed}");

        return self::SUCCESS;
    }
}
