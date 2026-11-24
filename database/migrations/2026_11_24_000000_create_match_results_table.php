<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico PERMANENTE de resultados, separado da sala.
 *
 * A sala (game_rooms) é operacional e some 2 minutos depois da vitória; com ela saem
 * participantes e partida por cascata. Os rankings semanal e mensal precisam da data
 * real de cada vitória, então cada resultado de conta é gravado aqui, uma vez
 * (unique match_id + user_id), sem chave estrangeira para a sala.
 *
 * Aditiva e reversível: nenhuma tabela existente é alterada nem apagada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('match_id');
            $table->uuid('user_id');
            $table->boolean('is_winner')->default(false);
            // Momento real da conclusão (UTC). Os rankings filtram por este campo.
            $table->timestamp('finished_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['match_id', 'user_id']);
            $table->index('finished_at');
            $table->index(['user_id', 'finished_at']);
            // Se a conta for excluída, o resultado dela sai junto (não há dado órfão).
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Partidas já concluídas viram resultados (idempotente: unique + insertOrIgnore).
        DB::table('game_matches as m')
            ->join('room_participants as p', 'p.room_id', '=', 'm.room_id')
            ->where('m.status', 'finished')
            ->whereNotNull('p.user_id')
            ->orderBy('m.id')
            ->select([
                'm.id as match_id', 'p.user_id', 'p.id as participant_id',
                'm.winner_participant_id', 'm.finished_at', 'm.updated_at',
            ])
            ->chunk(500, function ($rows): void {
                DB::table('match_results')->insertOrIgnore($rows->map(fn ($r) => [
                    'match_id' => $r->match_id,
                    'user_id' => $r->user_id,
                    'is_winner' => $r->winner_participant_id !== null && (int) $r->participant_id === (int) $r->winner_participant_id,
                    'finished_at' => $r->finished_at ?? $r->updated_at,
                    'created_at' => now(),
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_results');
    }
};
