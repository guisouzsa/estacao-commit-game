<?php

namespace App\Console\Commands;

use App\Models\GuestProfile;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

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

        // Só a contagem: nada de nomes, ids ou tokens nos logs.
        if ($removed > 0) {
            Log::info('Perfis temporários inativos removidos.', [
                'count' => $removed,
                'inactivity_hours' => (int) config('players.guest_ttl_hours'),
            ]);
        }

        $this->info("Perfis temporários removidos: {$removed}");

        return self::SUCCESS;
    }
}
