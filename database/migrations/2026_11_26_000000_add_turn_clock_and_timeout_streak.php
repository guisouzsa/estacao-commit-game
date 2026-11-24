<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relógio da vez (autoridade do servidor) e contagem de timeouts seguidos por jogador.
 * Só ADICIONA colunas com padrão seguro: nenhuma linha existente muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table): void {
            $table->timestamp('turn_started_at')->nullable();
        });
        Schema::table('room_participants', function (Blueprint $table): void {
            $table->unsignedTinyInteger('timeout_streak')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', fn (Blueprint $table) => $table->dropColumn('turn_started_at'));
        Schema::table('room_participants', fn (Blueprint $table) => $table->dropColumn('timeout_streak'));
    }
};
