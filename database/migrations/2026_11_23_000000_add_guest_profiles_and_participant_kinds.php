<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perfil temporário de visitante: NÃO é um usuário, não tem e-mail nem senha.
        Schema::create('guest_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('display_name', 24);
            // Nome normalizado (minúsculas, sem acentos): garante a unicidade.
            $table->string('name_key', 24)->unique();
            $table->string('avatar_style', 16);
            $table->string('avatar_seed', 32);
            // Só o hash do token fica no banco; o token vai apenas no cookie HttpOnly.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at')->index();
            // Preparado para a conversão em conta permanente (ainda sem interface).
            $table->uuid('converted_user_id')->nullable()->index();
            $table->timestamps();
        });

        // Participante deixa de ser sempre um usuário: pode ser visitante ou bot.
        Schema::table('room_participants', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();
        });
        Schema::table('room_participants', function (Blueprint $table): void {
            $table->uuid('guest_id')->nullable();
            $table->string('kind', 10)->default('user');
            $table->string('bot_key', 40)->nullable();
            $table->boolean('is_host')->default(false);
            $table->foreign('guest_id')->references('id')->on('guest_profiles')->cascadeOnDelete();
            $table->unique(['room_id', 'guest_id']);
            $table->unique(['room_id', 'bot_key']);
        });

        // O anfitrião pode ser um usuário ou um visitante.
        Schema::table('game_rooms', function (Blueprint $table): void {
            $table->uuid('host_user_id')->nullable()->change();
        });
        Schema::table('game_rooms', function (Blueprint $table): void {
            $table->uuid('host_guest_id')->nullable()->index();
            $table->unsignedTinyInteger('bot_slots')->default(0);
            $table->foreign('host_guest_id')->references('id')->on('guest_profiles')->cascadeOnDelete();
        });

        // Linhas já existentes: o anfitrião é o participante que é o host_user_id.
        DB::table('room_participants')
            ->whereExists(fn ($query) => $query
                ->from('game_rooms')
                ->whereColumn('game_rooms.id', 'room_participants.room_id')
                ->whereColumn('game_rooms.host_user_id', 'room_participants.user_id'))
            ->update(['is_host' => true]);

        if (DB::getDriverName() === 'pgsql') {
            // Cada participante é exatamente um: usuário, visitante ou bot.
            DB::statement("ALTER TABLE room_participants ADD CONSTRAINT room_participants_one_identity CHECK (
                (kind = 'user'  AND user_id IS NOT NULL AND guest_id IS NULL AND bot_key IS NULL) OR
                (kind = 'guest' AND guest_id IS NOT NULL AND user_id IS NULL AND bot_key IS NULL) OR
                (kind = 'bot'   AND bot_key IS NOT NULL AND user_id IS NULL AND guest_id IS NULL))");
            DB::statement('ALTER TABLE game_rooms ADD CONSTRAINT game_rooms_one_host CHECK (
                (host_user_id IS NOT NULL AND host_guest_id IS NULL) OR
                (host_user_id IS NULL AND host_guest_id IS NOT NULL))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE game_rooms DROP CONSTRAINT IF EXISTS game_rooms_one_host');
            DB::statement('ALTER TABLE room_participants DROP CONSTRAINT IF EXISTS room_participants_one_identity');
        }

        // Dados que só existem por causa desta funcionalidade não cabem no esquema antigo.
        DB::table('room_participants')->where('kind', '!=', 'user')->delete();
        DB::table('game_rooms')->whereNull('host_user_id')->delete();

        Schema::table('game_rooms', function (Blueprint $table): void {
            $table->dropForeign(['host_guest_id']);
            $table->dropIndex(['host_guest_id']);
            $table->dropColumn(['host_guest_id', 'bot_slots']);
        });
        Schema::table('game_rooms', function (Blueprint $table): void {
            $table->uuid('host_user_id')->nullable(false)->change();
        });

        Schema::table('room_participants', function (Blueprint $table): void {
            $table->dropForeign(['guest_id']);
            $table->dropUnique(['room_id', 'guest_id']);
            $table->dropUnique(['room_id', 'bot_key']);
            $table->dropColumn(['guest_id', 'kind', 'bot_key', 'is_host']);
        });
        Schema::table('room_participants', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable(false)->change();
        });

        Schema::dropIfExists('guest_profiles');
    }
};
