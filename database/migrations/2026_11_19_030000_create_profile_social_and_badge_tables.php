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
        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('description', 240);
            $table->string('category', 40)->default('milestone');
            $table->string('rarity', 20)->default('common');
            $table->string('image_path')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('secret')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('badge_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->string('granted_by')->nullable();
            $table->json('progress')->nullable();
            $table->unique(['user_id', 'badge_id']);
        });
        Schema::create('profile_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 30);
            $table->string('label', 60);
            $table->string('url', 500);
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('achievement_badge_id')->nullable()->index());

        $now = now();
        DB::table('badges')->insert([
            ['slug'=>'first-access','name'=>'Primeiro acesso','description'=>'Entrou na Estação Commit.','category'=>'journey','rarity'=>'common','sort_order'=>10,'created_at'=>$now,'updated_at'=>$now],
            ['slug'=>'first-step','name'=>'Primeiro passo','description'=>'Realizou sua primeira partida.','category'=>'game','rarity'=>'common','sort_order'=>20,'created_at'=>$now,'updated_at'=>$now],
            ['slug'=>'first-commit','name'=>'Primeiro commit','description'=>'Concluiu seu primeiro marco de contribuição.','category'=>'contribution','rarity'=>'uncommon','sort_order'=>30,'created_at'=>$now,'updated_at'=>$now],
            ['slug'=>'10-wins','name'=>'10 vitórias','description'=>'Alcançou dez vitórias verificadas.','category'=>'victory','rarity'=>'uncommon','sort_order'=>40,'created_at'=>$now,'updated_at'=>$now],
            ['slug'=>'25-wins','name'=>'25 vitórias','description'=>'Alcançou vinte e cinco vitórias verificadas.','category'=>'victory','rarity'=>'rare','sort_order'=>50,'created_at'=>$now,'updated_at'=>$now],
            ['slug'=>'50-wins','name'=>'50 vitórias','description'=>'Alcançou cinquenta vitórias verificadas.','category'=>'victory','rarity'=>'epic','sort_order'=>60,'created_at'=>$now,'updated_at'=>$now],
            ['slug'=>'100-wins','name'=>'100 vitórias','description'=>'Alcançou cem vitórias verificadas.','category'=>'victory','rarity'=>'legendary','sort_order'=>70,'created_at'=>$now,'updated_at'=>$now],
            ['slug'=>'veteran','name'=>'Veterano','description'=>'Alcançou um marco de participação.','category'=>'journey','rarity'=>'rare','sort_order'=>80,'created_at'=>$now,'updated_at'=>$now],
            ['slug'=>'contributor','name'=>'Contribuidor','description'=>'Alcançou um marco de contribuições.','category'=>'contribution','rarity'=>'rare','sort_order'=>90,'created_at'=>$now,'updated_at'=>$now],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('achievement_badge_id'));
        Schema::dropIfExists('profile_links'); Schema::dropIfExists('badge_user'); Schema::dropIfExists('badges');
    }
};
