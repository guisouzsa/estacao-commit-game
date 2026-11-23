<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GameMatch extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['room_id', 'status', 'current_seat', 'turn_number', 'version', 'state', 'winner_participant_id', 'finished_at'];

    protected function casts(): array
    {
        return ['state' => 'array', 'finished_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $match) => $match->id ??= (string) Str::uuid7());
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class, 'room_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(GameAction::class);
    }
}
