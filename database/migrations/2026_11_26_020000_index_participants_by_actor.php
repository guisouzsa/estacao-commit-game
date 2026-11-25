<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Em quais salas esta pessoa está?" (painel, entrar em sala, limpeza de visitantes) filtra room_participants por
 * user_id ou guest_id. As únicas existentes começam por room_id e, no PostgreSQL, chave estrangeira não cria índice.
 * Só adiciona índices; nenhuma linha muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_participants', function (Blueprint $table): void {
            $table->index('user_id', 'room_participants_user_id_index');
            $table->index('guest_id', 'room_participants_guest_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('room_participants', function (Blueprint $table): void {
            $table->dropIndex('room_participants_user_id_index');
            $table->dropIndex('room_participants_guest_id_index');
        });
    }
};
