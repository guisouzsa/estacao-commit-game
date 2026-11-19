<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('profile_title', 50)->nullable();
                $table->unsignedSmallInteger('avatar_position_x')->default(50);
                $table->unsignedSmallInteger('avatar_position_y')->default(50);
                $table->string('developer_badge', 40)->nullable();
            });

            return;
        }

        DB::statement(<<<'SQL'
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_title varchar(50) NULL,
    ADD COLUMN IF NOT EXISTS avatar_position_x smallint NOT NULL DEFAULT 50,
    ADD COLUMN IF NOT EXISTS avatar_position_y smallint NOT NULL DEFAULT 50,
    ADD COLUMN IF NOT EXISTS developer_badge varchar(40) NULL
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn([
                    'profile_title',
                    'avatar_position_x',
                    'avatar_position_y',
                    'developer_badge',
                ]);
            });

            return;
        }

        DB::statement(<<<'SQL'
ALTER TABLE users
    DROP COLUMN IF EXISTS profile_title,
    DROP COLUMN IF EXISTS avatar_position_x,
    DROP COLUMN IF EXISTS avatar_position_y,
    DROP COLUMN IF EXISTS developer_badge
SQL);
    }
};
