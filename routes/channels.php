<?php

use App\Models\GuestProfile;
use App\Models\RoomParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Contas e visitantes autenticam por guards diferentes ("web" e "visitor").
// Cada canal diz quais guards aceita; bots nunca assinam canais.

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user instanceof User && (string) $user->id === (string) $id;
}, ['guards' => ['web']]);

Broadcast::channel('App.Models.GuestProfile.{id}', function ($guest, $id) {
    return $guest instanceof GuestProfile && (string) $guest->id === (string) $id;
}, ['guards' => ['visitor']]);

// Só quem participa da sala (conta ou visitante) assina o canal dela.
Broadcast::channel('rooms.{roomId}', function ($actor, string $roomId) {
    return RoomParticipant::query()
        ->where('room_id', $roomId)
        ->forActor($actor)
        ->exists();
}, ['guards' => ['web', 'visitor']]);
