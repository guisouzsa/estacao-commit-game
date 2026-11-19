<?php

namespace App\Services;

use App\Events\RoomStateChanged;
use App\Models\GameRoom;
use Illuminate\Support\Facades\DB;

class RoomExpirationService
{
    public const WAITING_MINUTES = 5;

    public function expireIfEligible(GameRoom $candidate): bool
    {
        return DB::transaction(function () use ($candidate): bool {
            $room = GameRoom::query()
                ->whereKey($candidate->getKey())
                ->lockForUpdate()
                ->first();

            if (
                ! $room
                || $room->status !== 'waiting'
                || $room->created_at->gt(now()->subMinutes(self::WAITING_MINUTES))
                || $room->participants()
                    ->where('user_id', '!=', $room->host_user_id)
                    ->exists()
            ) {
                return false;
            }

            $room->update(['status' => 'expired']);
            $room->invitations()
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            RoomStateChanged::dispatch($room->id, 'expired');

            return true;
        }, 3);
    }

    public function expireDueRooms(): int
    {
        $expiredCount = 0;

        GameRoom::query()
            ->where('status', 'waiting')
            ->where('created_at', '<=', now()->subMinutes(self::WAITING_MINUTES))
            ->whereDoesntHave('participants', fn ($query) => $query
                ->whereColumn('room_participants.user_id', '!=', 'game_rooms.host_user_id'))
            ->select('id')
            ->chunkById(100, function ($rooms) use (&$expiredCount): void {
                foreach ($rooms as $room) {
                    if ($this->expireIfEligible($room)) {
                        $expiredCount++;
                    }
                }
            });

        return $expiredCount;
    }
}
