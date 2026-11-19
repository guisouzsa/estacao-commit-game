<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user->id === $id;
});

Broadcast::channel('rooms.{roomId}', function ($user, string $roomId) {
    return $user->roomParticipants()->where('room_id', $roomId)->exists();
});
