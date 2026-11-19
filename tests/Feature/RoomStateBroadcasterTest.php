<?php

namespace Tests\Feature;

use App\Services\RoomStateBroadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RoomStateBroadcasterTest extends TestCase
{
    public function test_broadcast_failure_is_logged_without_failing_the_committed_action(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(BroadcastException::class, 'Reverb unavailable.');

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Room state broadcast failed; polling remains available.',
                Mockery::on(fn (array $context): bool => $context['room_id'] === 'room-id'
                    && $context['change'] === 'participant_joined'
                    && $context['exception'] === BroadcastException::class)
            );

        DB::transaction(function () use ($dispatcher): void {
            (new RoomStateBroadcaster($dispatcher))->publish('room-id', 'participant_joined');
        });
    }
}
