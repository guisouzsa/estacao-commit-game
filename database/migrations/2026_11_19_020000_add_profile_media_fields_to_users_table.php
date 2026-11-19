<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_cover varchar(255) NULL,
    ADD COLUMN IF NOT EXISTS cover_position_x smallint NOT NULL DEFAULT 50,
    ADD COLUMN IF NOT EXISTS cover_position_y smallint NOT NULL DEFAULT 50,
    ADD COLUMN IF NOT EXISTS avatar_zoom smallint NOT NULL DEFAULT 100
SQL);
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('profile_cover')->nullable();
            $table->unsignedSmallInteger('cover_position_x')->default(50);
            $table->unsignedSmallInteger('cover_position_y')->default(50);
            $table->unsignedSmallInteger('avatar_zoom')->default(100);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
ALTER TABLE users
    DROP COLUMN IF EXISTS profile_cover,
    DROP COLUMN IF EXISTS cover_position_x,
    DROP COLUMN IF EXISTS cover_position_y,
    DROP COLUMN IF EXISTS avatar_zoom
SQL);
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['profile_cover', 'cover_position_x', 'cover_position_y', 'avatar_zoom']);
        });
    }
};
