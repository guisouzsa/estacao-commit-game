<?php

namespace App\Services;

use App\Models\GameRoom;
use App\Notifications\RoomDeleted;
use App\Support\Actor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Exclui salas que ficaram vazias (só o anfitrião humano; bots não contam)
 * por 5 minutos.
 *
 * Toda a decisão é do servidor e é refeita dentro de uma transação, com a
 * linha da sala bloqueada. Assim uma execução atrasada do scheduler, duas
 * execuções simultâneas ou uma entrada de jogador no mesmo instante nunca
 * excluem uma sala que deixou de estar vazia.
 */
class RoomExpirationService
{
    public const WAITING_MINUTES = 5;

    /**
     * Exclui a sala se ela continuar vazia e vencida. Retorna true se excluiu.
     * Aceita um modelo possivelmente desatualizado: o estado real é relido.
     */
    public function expireIfEligible(GameRoom $candidate): bool
    {
        return DB::transaction(function () use ($candidate): bool {
            $room = GameRoom::query()
                ->whereKey($candidate->getKey())
                ->lockForUpdate()
                ->first();

            if (! $room || ! $this->isOverdue($room)) {
                return false;
            }

            $host = $room->hostActor();

            // Mesma transação da exclusão: ou existem os dois, ou nenhum.
            $host?->notify(new RoomDeleted($room->id, RoomDeleted::EMPTY_TIMEOUT));
            $room->delete();

            $broadcaster = app(RoomStateBroadcaster::class);
            $broadcaster->publish($room->id, 'deleted');
            if ($host) {
                $broadcaster->publishToActor($host);
            }

            return true;
        }, 3);
    }

    /** Processa todas as salas vencidas. Idempotente: pode rodar a qualquer momento. */
    public function expireDueRooms(): int
    {
        $expiredCount = 0;

        $this->dueRooms()
            ->select('id')
            ->chunkById(100, function ($rooms) use (&$expiredCount): void {
                foreach ($rooms as $room) {
                    if ($this->expireIfEligible($room)) {
                        $expiredCount++;
                    }
                }
            });

        return $expiredCount;
    }

    /**
     * Verificação complementar ao scheduler, limitada às salas de um anfitrião
     * (conta ou visitante), usada ao abrir a Dashboard: poucas linhas, consulta indexada.
     */
    public function expireDueRoomsHostedBy(Authenticatable $actor): int
    {
        $expiredCount = 0;

        $rooms = $this->dueRooms()
            ->where(Actor::hostColumn($actor), Actor::id($actor))
            ->limit(10)
            ->get();

        foreach ($rooms as $room) {
            if ($this->expireIfEligible($room)) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * Mantém `empty_since` coerente com os participantes: preenche quando a
     * sala passa a ter só o anfitrião humano e limpa quando outro humano está
     * nela. Deve ser chamado por quem já bloqueou a sala.
     */
    public function syncEmptiness(GameRoom $room): void
    {
        $waiting = $room->status === 'waiting' && $room->started_at === null;
        $shouldBeEmpty = $waiting && ! $room->otherHumans()->exists();

        if ($shouldBeEmpty && $room->empty_since === null) {
            $room->forceFill(['empty_since' => now()])->save();
        } elseif (! $shouldBeEmpty && $room->empty_since !== null) {
            $room->forceFill(['empty_since' => null])->save();
        }
    }

    /** Momento em que a contagem de uma sala vazia termina. */
    public function deadline(GameRoom $room): ?Carbon
    {
        return $room->empty_since?->copy()->addMinutes(self::WAITING_MINUTES);
    }

    /** Salas aguardando, vazias e com a contagem vencida (candidatas; o estado é rechecado com lock). */
    private function dueRooms(): Builder
    {
        return GameRoom::query()
            ->where('status', 'waiting')
            ->whereNull('started_at')
            ->whereNotNull('empty_since')
            ->where('empty_since', '<=', $this->cutoff());
    }

    /**
     * Teste barato, em memória (sem consulta): a sala PODE estar vencida? Só então vale abrir a transação com trava
     * de expireIfEligible, que confirma de verdade. Evita uma ida ao banco em toda entrada por convite.
     */
    public function mightBeOverdue(GameRoom $room): bool
    {
        return $room->status === 'waiting'
            && $room->started_at === null
            && $room->empty_since !== null
            && $room->empty_since->lte($this->cutoff());
    }

    private function isOverdue(GameRoom $room): bool
    {
        return $room->status === 'waiting'
            && $room->started_at === null
            && $room->empty_since !== null
            && $room->empty_since->lte($this->cutoff())
            && ! $room->otherHumans()->exists();
    }

    private function cutoff(): Carbon
    {
        return now()->subMinutes(self::WAITING_MINUTES);
    }
}
