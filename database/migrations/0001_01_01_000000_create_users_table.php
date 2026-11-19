<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE users (
    id uuid PRIMARY KEY,
    username varchar(30) NOT NULL,
    display_name varchar(50) NOT NULL,
    bio text NULL,
    avatar varchar(255) NULL,
    profile_frame varchar(255) NULL,
    primary_badge varchar(255) NULL,
    email varchar(255) NOT NULL UNIQUE,
    email_verified_at timestamp(0) NULL,
    password varchar(255) NOT NULL,
    remember_token varchar(100) NULL,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL
)
SQL);
        DB::statement('CREATE UNIQUE INDEX users_username_lower_unique ON users (LOWER(username))');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
