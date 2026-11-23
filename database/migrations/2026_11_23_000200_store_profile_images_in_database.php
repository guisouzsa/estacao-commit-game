<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE users ALTER COLUMN avatar TYPE text, ALTER COLUMN profile_cover TYPE text'),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE users MODIFY avatar LONGTEXT NULL, MODIFY profile_cover LONGTEXT NULL'),
            // SQLite não aplica o limite declarado de VARCHAR e aceita o data URI completo.
            default => null,
        };
    }

    public function down(): void
    {
        // Não reduzimos novamente para varchar(255): isso truncaria imagens já salvas.
    }
};
