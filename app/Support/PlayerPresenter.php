<?php

namespace App\Support;

use App\Models\GuestProfile;
use App\Models\RoomParticipant;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Forma ÚNICA de enviar um jogador ao frontend, seja conta, visitante ou bot.
 * Todo componente de avatar recebe este mesmo formato, então a navbar, a
 * Dashboard e a sala nunca divergem.
 *
 * Nunca inclui e-mail, token ou qualquer dado de conta.
 */
class PlayerPresenter
{
    /**
     * Semente do avatar de uma conta: estável, mas derivada por hash para não
     * expor o ID do usuário em páginas públicas.
     */
    public static function seed(string $userId): string
    {
        return substr(hash('sha256', 'avatar-seed|'.$userId), 0, 12);
    }

    /**
     * Como a conta aparece: foto enviada, ícone gerado escolhido ("gen:estilo:semente"
     * em users.avatar) ou, sem nada disso, o ícone padrão derivado do ID.
     * Único ponto que decide isso: navbar, Dashboard, sala, busca e perfil usam este.
     *
     * @return array{avatar: ?string, avatar_style: string, avatar_seed: string}
     */
    public static function avatarFields(User $user): array
    {
        $raw = (string) $user->avatar;

        if (preg_match('/^gen:([a-z]+):([a-z0-9]{6,32})$/', $raw, $match)
            && in_array($match[1], config('players.avatar_styles'), true)) {
            return ['avatar' => null, 'avatar_style' => $match[1], 'avatar_seed' => $match[2]];
        }

        return [
            'avatar' => AvatarUrl::forUser($user),
            'avatar_style' => 'pixel',
            'avatar_seed' => self::seed((string) $user->id),
        ];
    }

    public static function actor(Authenticatable $actor): array
    {
        return $actor instanceof GuestProfile ? self::guest($actor) : self::user($actor);
    }

    public static function user(User $user): array
    {
        return [
            'key' => 'user:'.$user->id,
            'kind' => 'user',
            'is_bot' => false,
            'username' => $user->username,
            'name' => $user->username,
            // Foto enviada ou ícone escolhido (ver avatarFields). Sem foto, o front desenha pela semente.
            ...self::avatarFields($user),
            'avatar_position_x' => $user->avatar_position_x ?? 50,
            'avatar_position_y' => $user->avatar_position_y ?? 50,
            'avatar_zoom' => $user->avatar_zoom ?? 100,
            'profile_frame' => $user->profile_frame ?? 'standard',
            'profile_background' => $user->profile_background,
            // Prévia de perfil na partida: dados que já vêm na linha do usuário (sem consulta extra).
            'profile_title' => $user->profile_title,
            'primary_badge' => $user->primary_badge,
            'matches_won' => (int) ($user->matches_won ?? 0),
        ];
    }

    public static function guest(GuestProfile $guest): array
    {
        return [
            'key' => 'guest:'.$guest->id,
            'kind' => 'guest',
            'is_bot' => false,
            'username' => $guest->display_name,
            'name' => $guest->display_name,
            'avatar' => null,
            'avatar_style' => $guest->avatar_style,
            'avatar_seed' => $guest->avatar_seed,
            'profile_frame' => 'standard',
        ];
    }

    public static function bot(string $botKey): array
    {
        $bot = Bots::find($botKey);

        return [
            'key' => 'bot:'.$botKey,
            'kind' => 'bot',
            'is_bot' => true,
            'username' => $bot['name'] ?? 'Bot',
            'name' => $bot['name'] ?? 'Bot',
            'avatar' => null,
            'avatar_style' => $bot['style'] ?? 'robot',
            'avatar_seed' => $botKey,
            'profile_frame' => 'terminal',
        ];
    }

    /** Exige `user` e `guest` já carregados (with) para não gerar N+1. */
    public static function participant(RoomParticipant $participant): array
    {
        $player = match ($participant->kind) {
            'bot' => self::bot((string) $participant->bot_key),
            'guest' => $participant->guest ? self::guest($participant->guest) : null,
            default => $participant->user ? self::user($participant->user) : null,
        };

        return [
            'id' => $participant->id,
            'seat' => $participant->seat,
            'is_host' => $participant->is_host,
            'player' => $player,
        ];
    }
}
