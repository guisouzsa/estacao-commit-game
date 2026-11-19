<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('matches_played')->nullable();
            $table->unsignedInteger('matches_won')->nullable();
            $table->unsignedInteger('matches_lost')->nullable();
        });

        DB::table('users')
            ->whereRaw('LOWER(username) = ?', ['gui'])
            ->update([
                'matches_won' => 100,
                'matches_lost' => 20,
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['matches_played', 'matches_won', 'matches_lost']);
        });
    }
};
