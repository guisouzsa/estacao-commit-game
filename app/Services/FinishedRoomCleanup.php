<?php

namespace App\Services;

use App\Models\GameRoom;
use App\Support\Actor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Exclui salas cuja partida terminou há mais de `game.finished_room_ttl_minutes` (2 min).
 *
 * Separa a existência OPERACIONAL da sala do histórico: o resultado fica em match_results
 * (sem chave estrangeira para a sala); participantes e partida saem por cascata.
 * Roda pelo scheduler (sem ninguém conectado) e também sob demanda (Dashboard e página da
 * sala), então o resultado é o mesmo com o scheduler atrasado. Idempotente: o estado é
 * relido com a linha bloqueada e uma sala ativa NUNCA é atingida.
 */
class FinishedRoomCleanup
{
    /** Momento a partir do qual a sala pode ser excluída. */
    public function deadline(GameRoom $room): ?Carbon
    {
        return $room->status === 'finished' && $room->finished_at
            ? $room->finished_at->copy()->addMinutes((int) config('game.finished_room_ttl_minutes'))
            : null;
    }

    /** Exclui se vencida. Aceita um modelo desatualizado: o estado real é relido. */
    public function purgeIfDue(GameRoom $candidate): bool
    {
        $roomId = $candidate->getKey();
        $humans = [];

        $deleted = DB::transaction(function () use ($roomId, &$humans): bool {
            $room = GameRoom::query()->whereKey($roomId)->lockForUpdate()->first();
            if (! $room || ! $this->isDue($room)) {
                return false;
            }

            $humans = $room->participants()->with(['user', 'guest'])->get()
                ->filter(fn ($p) => $p->kind !== 'bot')
                ->map(fn ($p) => $p->user ?? $p->guest)->filter()->values()->all();

            $room->delete(); // FKs: participantes, convites e partida saem junto

            return true;
        }, 3);

        if ($deleted) {
            $broadcaster = app(RoomStateBroadcaster::class);
            $broadcaster->publish($roomId, 'deleted');
            foreach ($humans as $human) {
                $broadcaster->publishToActor($human);
            }
        }

        return $deleted;
    }

    /** Processa todas as salas vencidas (scheduler). Retorna quantas foram excluídas. */
    public function purgeDue(): int
    {
        $count = 0;
        $this->dueQuery()->select('id')->chunkById(100, function ($rooms) use (&$count): void {
            foreach ($rooms as $room) {
                $count += (int) $this->purgeIfDue($room);
            }
        });

        if ($count > 0) {
            Log::info('Salas de partidas concluídas excluídas.', ['count' => $count]);
        }

        return $count;
    }

    /** Verificação leve, limitada às salas de uma pessoa (Dashboard). */
    public function purgeDueFor(Authenticatable $actor): int
    {
        $count = 0;
        $rooms = $this->dueQuery()
            ->whereIn('id', DB::table('room_participants')->where(Actor::participantColumn($actor), Actor::id($actor))->select('room_id'))
            ->limit(10)->get(['id']);
        foreach ($rooms as $room) {
            $count += (int) $this->purgeIfDue($room);
        }

        return $count;
    }

    private function dueQuery(): Builder
    {
        return GameRoom::query()
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->where('finished_at', '<=', $this->cutoff());
    }

    private function isDue(GameRoom $room): bool
    {
        return $room->status === 'finished' && $room->finished_at !== null && $room->finished_at->lte($this->cutoff());
    }

    private function cutoff(): Carbon
    {
        return now()->subMinutes((int) config('game.finished_room_ttl_minutes'));
    }
}
