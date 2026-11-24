<?php

namespace App\Services;

use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;

/**
 * Grava o resultado PERMANENTE de uma partida concluída (tabela match_results).
 * Idempotente: a chave única (match_id, user_id) + insertOrIgnore garantem que a
 * mesma vitória nunca conta duas vezes, mesmo com eventos repetidos ou reconexões.
 * Só contas entram: visitantes e bots não têm ranking.
 */
class MatchResults
{
    /** @return int quantos resultados novos foram gravados */
    public static function record(GameMatch $match): int
    {
        if ($match->status !== 'finished') {
            return 0;
        }

        $at = $match->finished_at ?? now();
        $rows = DB::table('room_participants')
            ->where('room_id', $match->room_id)
            ->whereNotNull('user_id')
            ->get(['id', 'user_id'])
            ->map(fn ($p) => [
                'match_id' => $match->id,
                'user_id' => $p->user_id,
                'is_winner' => $match->winner_participant_id !== null && (int) $p->id === (int) $match->winner_participant_id,
                'finished_at' => $at,
                'created_at' => now(),
            ])->all();

        return $rows === [] ? 0 : DB::table('match_results')->insertOrIgnore($rows);
    }
}
