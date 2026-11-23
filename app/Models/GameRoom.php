<?php

namespace App\Models;

use App\Support\Actor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class GameRoom extends Model
{
    public const MAX_PARTICIPANTS = 4;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['host_user_id', 'host_guest_id', 'status', 'started_at', 'finished_at', 'empty_since', 'bot_slots'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'empty_since' => 'datetime', 'bot_slots' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $room) => $room->id ??= (string) Str::uuid7());
    }

    /** Anfitrião quando é uma conta permanente. */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /** Anfitrião quando é um visitante. */
    public function hostGuest(): BelongsTo
    {
        return $this->belongsTo(GuestProfile::class, 'host_guest_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(RoomParticipant::class, 'room_id')->orderBy('seat');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(RoomInvitation::class, 'room_id');
    }

    public function gameMatch(): HasOne
    {
        return $this->hasOne(GameMatch::class, 'room_id');
    }

    public function hostActor(): ?Authenticatable
    {
        return $this->host_user_id !== null ? $this->host : $this->hostGuest;
    }

    public function isHostedBy(Authenticatable $actor): bool
    {
        return $this->getAttribute(Actor::hostColumn($actor)) === Actor::id($actor);
    }

    /** Participantes humanos além do anfitrião (bots não contam). */
    public function otherHumans(): HasMany
    {
        return $this->participants()->where('is_host', false)->where('kind', '!=', 'bot');
    }

    public function hasParticipant(Authenticatable $actor): bool
    {
        return $this->participants()->where(Actor::participantColumn($actor), Actor::id($actor))->exists();
    }

    /** Vagas que sobram para humanos (4 menos os bots escolhidos na criação). */
    public function humanSeats(): int
    {
        return self::MAX_PARTICIPANTS - (int) $this->bot_slots;
    }
}
