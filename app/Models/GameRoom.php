<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GameRoom extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['host_user_id', 'status', 'started_at', 'finished_at'];
    protected function casts(): array { return ['started_at'=>'datetime','finished_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn (self $room) => $room->id ??= (string) Str::uuid7()); }
    public function host(): BelongsTo { return $this->belongsTo(User::class, 'host_user_id'); }
    public function participants(): HasMany { return $this->hasMany(RoomParticipant::class, 'room_id')->orderBy('seat'); }
    public function invitations(): HasMany { return $this->hasMany(RoomInvitation::class, 'room_id'); }
}
