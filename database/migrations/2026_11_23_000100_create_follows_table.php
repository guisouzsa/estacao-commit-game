<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Só contas permanentes se seguem. Visitantes e bots não entram aqui.
        Schema::create('follows', function (Blueprint $table): void {
            $table->id();
            $table->uuid('follower_id');
            $table->uuid('followed_id');
            $table->timestamp('created_at')->useCurrent();

            // Impede relacionamento duplicado, mesmo com dois cliques ao mesmo tempo.
            $table->unique(['follower_id', 'followed_id']);
            // Listas paginadas e contagens: "seguidores de X" e "quem X segue".
            $table->index(['followed_id', 'created_at']);
            $table->index(['follower_id', 'created_at']);

            $table->foreign('follower_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('followed_id')->references('id')->on('users')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE follows ADD CONSTRAINT follows_not_self CHECK (follower_id <> followed_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
