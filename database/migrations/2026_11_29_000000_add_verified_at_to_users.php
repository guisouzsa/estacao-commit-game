<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selo de "perfil verificado" (o check azul ao lado do nome).
 * Só adiciona uma coluna anulável: ninguém nasce verificado e nada existente muda.
 * Quem concede o selo é a conta administradora (PROFILE_ADMIN_EMAILS), pela caixa no próprio perfil.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'verified_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('verified_at');
            });
        }
    }
};
