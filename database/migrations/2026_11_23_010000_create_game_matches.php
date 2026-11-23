<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('room_id')->unique();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedTinyInteger('current_seat');
            $table->unsignedInteger('turn_number')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->json('state');
            $table->unsignedBigInteger('winner_participant_id')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->foreign('room_id')->references('id')->on('game_rooms')->cascadeOnDelete();
            $table->foreign('winner_participant_id')->references('id')->on('room_participants')->nullOnDelete();
        });

        Schema::create('game_actions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('game_match_id');
            $table->uuid('action_uuid');
            $table->unsignedBigInteger('participant_id');
            $table->string('action', 32);
            $table->json('payload')->nullable();
            $table->unsignedInteger('resulting_version');
            $table->timestamp('created_at');
            $table->unique(['game_match_id', 'action_uuid']);
            $table->foreign('game_match_id')->references('id')->on('game_matches')->cascadeOnDelete();
            $table->foreign('participant_id')->references('id')->on('room_participants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_actions');
        Schema::dropIfExists('game_matches');
    }
};
