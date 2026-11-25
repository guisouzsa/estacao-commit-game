<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice para a varredura automática de vezes paradas (games:sweep): "partidas ativas cuja vez começou
 * há mais de X segundos". Só adiciona um índice; nenhuma linha muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table): void {
            $table->index(['status', 'turn_started_at'], 'game_matches_status_turn_clock_index');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table): void {
            $table->dropIndex('game_matches_status_turn_clock_index');
        });
    }
};
