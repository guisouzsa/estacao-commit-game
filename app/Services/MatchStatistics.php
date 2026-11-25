<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\GameRoom;

/** Fecha a sala e soma o placar das contas. Compartilhado pelo motor PHP e pelo callback da Cloudflare. */
class MatchStatistics
{
    public static function record(GameRoom $room, GameMatch $match): void
    {
        \App\Support\OpsLog::event('game.finished', ['room' => $room->id, 'match' => $match->id, 'winner' => $match->winner_participant_id]);

        // O ciclo de vida da sala precisa terminar junto com a partida. A limpeza
        // de visitantes usa este estado para preservar apenas partidas em andamento.
        $room->forceFill([
            'status' => 'finished',
            'finished_at' => $match->finished_at ?? now(),
        ])->save();

        $room->participants()->with('user')->whereNotNull('user_id')->get()->each(function ($participant) use ($match): void {
            $user = $participant->user;
            $user->matches_played = ($user->matches_played ?? 0) + 1;
            if ($participant->id === $match->winner_participant_id) {
                $user->matches_won = ($user->matches_won ?? 0) + 1;
                $user->matches_lost ??= 0;
            } else {
                $user->matches_lost = ($user->matches_lost ?? 0) + 1;
                $user->matches_won ??= 0;
            }
            $user->save();
        });
    }
}
