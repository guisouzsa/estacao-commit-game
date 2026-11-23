<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Algumas instalações receberam estas colunas pelo SQL manual antes de a
        // migration entrar no histórico. Verificar uma a uma torna a execução
        // idempotente e preserva os dados já existentes.
        foreach (['matches_played', 'matches_won', 'matches_lost'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column): void {
                    $table->unsignedInteger($column)->nullable();
                });
            }
        }

        DB::table('users')
            ->whereRaw('LOWER(username) = ?', ['gui'])
            ->update([
                'matches_won' => 100,
                'matches_lost' => 20,
            ]);
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['matches_played', 'matches_won', 'matches_lost'],
            fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
