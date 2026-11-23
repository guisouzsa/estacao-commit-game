<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Perfil temporário de quem joga sem criar conta. Não é um User: não tem
 * e-mail, senha nem permissões de conta. Autentica pelo guard "visitor".
 */
class GuestProfile extends Model implements AuthenticatableContract
{
    use Authenticatable, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'display_name', 'name_key', 'avatar_style', 'avatar_seed',
        'token_hash', 'last_seen_at', 'expires_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $guest) => $guest->id ??= (string) Str::uuid7());
    }

    /** Visitantes não usam "lembrar-me": o token fica só no cookie. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function participations(): HasMany
    {
        return $this->hasMany(RoomParticipant::class, 'guest_id');
    }

    /** Perfis cuja janela de inatividade acabou. */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Perfis que podem ser apagados: nunca quem está em uma partida em andamento
     * (sala iniciada e ainda não encerrada).
     */
    public function scopeWithoutActiveMatch(Builder $query): Builder
    {
        return $query->whereNotExists(fn ($sub) => $sub
            ->from('room_participants')
            ->join('game_rooms', 'game_rooms.id', '=', 'room_participants.room_id')
            ->whereColumn('room_participants.guest_id', 'guest_profiles.id')
            ->whereNotNull('game_rooms.started_at')
            ->whereNull('game_rooms.finished_at'));
    }
}
