<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomParticipant extends Model
{
    protected $fillable = ['room_id','user_id','seat','joined_at'];
    protected function casts(): array { return ['joined_at'=>'datetime']; }
    public function room(): BelongsTo { return $this->belongsTo(GameRoom::class, 'room_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
