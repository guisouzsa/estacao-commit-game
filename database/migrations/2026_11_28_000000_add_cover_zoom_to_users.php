<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoom da capa do perfil, em % do enquadramento "preencher" (100 = como as capas sempre foram exibidas).
 * Só adiciona a coluna: capas já configuradas ficam com 100 e aparecem exatamente iguais.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'cover_zoom')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('cover_zoom')->default(100);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'cover_zoom')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('cover_zoom');
            });
        }
    }
};
