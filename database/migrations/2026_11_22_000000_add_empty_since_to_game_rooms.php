<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_rooms', function (Blueprint $table): void {
            // Momento em que a sala ficou só com o anfitrião. Nulo quando há
            // outros jogadores ou a sala não está mais aguardando.
            $table->timestamp('empty_since')->nullable();
            $table->index(['status', 'empty_since']);
        });

        // Salas que já aguardam sozinhas passam a contar a partir da criação,
        // o mesmo prazo que valia antes desta coluna.
        DB::table('game_rooms')
            ->where('status', 'waiting')
            ->whereNull('started_at')
            ->whereNotExists(fn ($query) => $query
                ->from('room_participants')
                ->whereColumn('room_participants.room_id', 'game_rooms.id')
                ->whereColumn('room_participants.user_id', '!=', 'game_rooms.host_user_id'))
            ->update(['empty_since' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('game_rooms', function (Blueprint $table): void {
            $table->dropIndex(['status', 'empty_since']);
            $table->dropColumn('empty_since');
        });
    }
};
