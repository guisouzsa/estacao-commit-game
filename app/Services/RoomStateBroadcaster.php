<?php

namespace App\Services;

use App\Events\RoomStateChanged;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomStateBroadcaster
{
    public function __construct(private Dispatcher $events) {}

    public function publish(string $roomId, string $change): void
    {
        DB::afterCommit(function () use ($roomId, $change): void {
            try {
                $this->events->dispatch(new RoomStateChanged($roomId, $change));
            } catch (BroadcastException $exception) {
                Log::warning('Room state broadcast failed; polling remains available.', [
                    'room_id' => $roomId,
                    'change' => $change,
                    'exception' => $exception::class,
                ]);
            }
        });
    }
}
