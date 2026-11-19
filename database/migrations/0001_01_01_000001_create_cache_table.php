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
        DB::statement('CREATE TABLE cache ("key" varchar(255) PRIMARY KEY, value text NOT NULL, expiration integer NOT NULL)');
        DB::statement('CREATE INDEX cache_expiration_index ON cache (expiration)');
        DB::statement('CREATE TABLE cache_locks ("key" varchar(255) PRIMARY KEY, owner varchar(255) NOT NULL, expiration integer NOT NULL)');
        DB::statement('CREATE INDEX cache_locks_expiration_index ON cache_locks (expiration)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
