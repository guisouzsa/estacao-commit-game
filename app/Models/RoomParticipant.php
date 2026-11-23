<?php

namespace App\Models;

use App\Support\Actor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um lugar na sala. É exatamente um destes: usuário, visitante ou bot
 * (`kind`). Uma única tabela e um único modelo para os três.
 */
class RoomParticipant extends Model
{
    protected $fillable = ['room_id','user_id','guest_id','kind','bot_key','is_host','seat','joined_at'];
    protected function casts(): array { return ['joined_at'=>'datetime','is_host'=>'boolean']; }

    protected static function booted(): void
    {
        // Quem cria o participante sem informar is_host ainda recebe o valor correto.
        static::creating(function (self $participant): void {
            if ($participant->getAttribute('is_host') !== null && array_key_exists('is_host', $participant->getAttributes())) {
                return;
            }

            $room = GameRoom::query()->find($participant->room_id, ['id', 'host_user_id', 'host_guest_id']);

            $participant->is_host = $room !== null && (
                ($participant->user_id !== null && $participant->user_id === $room->host_user_id)
                || ($participant->guest_id !== null && $participant->guest_id === $room->host_guest_id)
            );
        });
    }

    public function room(): BelongsTo { return $this->belongsTo(GameRoom::class, 'room_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function guest(): BelongsTo { return $this->belongsTo(GuestProfile::class, 'guest_id'); }

    public function isBot(): bool { return $this->kind === 'bot'; }

    public function scopeForActor(Builder $query, Authenticatable $actor): Builder
    {
        return $query->where(Actor::participantColumn($actor), Actor::id($actor));
    }
}
