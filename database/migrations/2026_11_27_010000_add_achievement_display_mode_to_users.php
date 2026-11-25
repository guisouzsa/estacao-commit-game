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
            $table->string('achievement_display_mode', 12)->default('none');
        });

        DB::table('users')->whereIn('id', DB::table('badge_user')->whereNotNull('featured_order')->select('user_id'))
            ->update(['achievement_display_mode' => 'custom']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('achievement_display_mode');
        });
    }
};
