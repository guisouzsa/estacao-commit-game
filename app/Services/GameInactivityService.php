<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\GameRoom;
use Illuminate\Support\Facades\DB;

/**
 * Evita que a mesa trave quando um humano some na própria vez.
 *
 * Sem processo extra: a verificação acontece quando alguém da mesa abre ou recarrega a
 * partida (o front já sincroniza de tempos em tempos). Se o jogador da vez é humano e a
 * partida não anda há `game.turn_timeout_seconds`, um bot assume a posição — mesmo lugar,
 * mesma mão e mesmo percurso — e a partida segue com o mesmo motor de regras.
 * A substituição é definitiva: quem foi substituído não volta à mesa.
 */
class GameInactivityService
{
    public function __construct(private readonly GameEngine $engine) {}

    /** @return bool true quando um bot assumiu uma posição (a mesa mudou). */
    public function sweep(GameRoom $room): bool
    {
        $timeout = (int) config('game.turn_timeout_seconds');
        if ($timeout <= 0) {
            return false;
        }

        return DB::transaction(function () use ($room, $timeout): bool {
            $match = GameMatch::query()->where('room_id', $room->id)->lockForUpdate()->first();
            if (! $match || $match->status !== 'active' || $match->updated_at->gt(now()->subSeconds($timeout))) {
                return false;
            }

            $participants = $room->participants()->get();
            $idle = $participants->firstWhere('seat', $match->current_seat);
            if (! $idle || $idle->isBot()) {
                return false;
            }

            app(RoomLeaveService::class)->replaceWithBot($room, $idle, 'ficou parado e um bot assumiu a posição por inatividade.');

            return true;
        }, 3);
    }
}
