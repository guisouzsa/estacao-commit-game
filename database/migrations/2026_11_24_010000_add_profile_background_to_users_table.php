<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fundo de perfil escolhido entre os presets (CSS/SVG leve, sem imagem). Nulo = comportamento
 * antigo (sem fundo escolhido), então perfis existentes continuam iguais. Aditiva e reversível.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('profile_background', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('profile_background');
        });
    }
};
