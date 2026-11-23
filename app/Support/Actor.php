<?php

namespace App\Support;

use App\Models\GuestProfile;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Quem está agindo: uma conta permanente (User) ou um visitante (GuestProfile).
 * Bots nunca são "atores": não autenticam e não chamam rotas.
 *
 * Centraliza a diferença de coluna para que o código de salas não tenha duas
 * versões da mesma lógica.
 */
class Actor
{
    public static function isGuest(Authenticatable $actor): bool
    {
        return $actor instanceof GuestProfile;
    }

    /** Coluna de room_participants que identifica este ator. */
    public static function participantColumn(Authenticatable $actor): string
    {
        return self::isGuest($actor) ? 'guest_id' : 'user_id';
    }

    /** Coluna de game_rooms que identifica o anfitrião. */
    public static function hostColumn(Authenticatable $actor): string
    {
        return self::isGuest($actor) ? 'host_guest_id' : 'host_user_id';
    }

    public static function kind(Authenticatable $actor): string
    {
        return self::isGuest($actor) ? 'guest' : 'user';
    }

    public static function id(Authenticatable $actor): string
    {
        return (string) $actor->getAuthIdentifier();
    }

    /** Canal privado do ator para avisos em tempo real. */
    public static function channel(Authenticatable $actor): string
    {
        return (self::isGuest($actor) ? 'App.Models.GuestProfile.' : 'App.Models.User.').self::id($actor);
    }
}
