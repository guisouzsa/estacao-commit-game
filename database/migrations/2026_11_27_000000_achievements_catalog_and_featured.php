<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conquistas: (1) `badge_user.featured_order` guarda as insígnias que a pessoa destaca no perfil (nulo = não destacada);
 * (2) sincroniza o catálogo de config/achievements.php com a tabela `badges` (upsert por slug: idempotente, nunca apaga
 * linha nem desbloqueio). Conquistas antigas sem critério ficam apenas inativas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_user', function (Blueprint $table): void {
            $table->unsignedTinyInteger('featured_order')->nullable();
        });

        $config = require base_path('config/achievements.php');
        $now = now();
        foreach ($config['catalog'] as $badge) {
            $values = [
                'name' => $badge['name'], 'description' => $badge['description'], 'category' => $badge['category'],
                'rarity' => $badge['rarity'], 'sort_order' => $badge['sort'], 'active' => true, 'updated_at' => $now,
            ];
            if (DB::table('badges')->where('slug', $badge['slug'])->exists()) {
                DB::table('badges')->where('slug', $badge['slug'])->update($values);
            } else {
                DB::table('badges')->insert($values + ['slug' => $badge['slug'], 'secret' => false, 'created_at' => $now]);
            }
        }
        DB::table('badges')->whereIn('slug', $config['retired'])->update(['active' => false, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::table('badge_user', function (Blueprint $table): void {
            $table->dropColumn('featured_order');
        });
    }
};
