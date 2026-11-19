<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('host_user_id')->index();
            $table->string('status', 20)->default('waiting')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->foreign('host_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('room_participants', function (Blueprint $table) {
            $table->id();
            $table->uuid('room_id');
            $table->uuid('user_id');
            $table->unsignedTinyInteger('seat');
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->unique(['room_id', 'user_id']);
            $table->unique(['room_id', 'seat']);
            $table->foreign('room_id')->references('id')->on('game_rooms')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('room_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('room_id')->index();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign('room_id')->references('id')->on('game_rooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_invitations');
        Schema::dropIfExists('room_participants');
        Schema::dropIfExists('game_rooms');
    }
};
