<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selo de "perfil verificado" (o check azul ao lado do nome) e a permissão de concedê-lo.
 * Só adiciona colunas: ninguém nasce verificado nem com poder de conceder. A permissão
 * `can_grant_verified` NÃO tem tela, rota nem campo de formulário: só é ligada por SQL, por quem administra o banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('verified_at')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'can_grant_verified')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('can_grant_verified')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['verified_at', 'can_grant_verified'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
