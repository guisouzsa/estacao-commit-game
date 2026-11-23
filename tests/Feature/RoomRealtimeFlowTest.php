<?php

namespace Tests\Feature;

use App\Events\RoomStateChanged;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\RoomStateBroadcaster;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/** O evento só é emitido depois do commit e nunca impede a operação principal. */
class RoomRealtimeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_is_dispatched_only_after_the_database_commit(): void
    {
        Event::fake([RoomStateChanged::class]);

        DB::transaction(function (): void {
            app(RoomStateBroadcaster::class)->publish('room-id', 'participant_joined');

            Event::assertNotDispatched(RoomStateChanged::class);
        });

        Event::assertDispatched(
            RoomStateChanged::class,
            fn (RoomStateChanged $event) => $event->roomId === 'room-id' && $event->change === 'participant_joined',
        );
    }

    public function test_event_is_not_dispatched_when_the_transaction_is_rolled_back(): void
    {
        Event::fake([RoomStateChanged::class]);

        try {
            DB::transaction(function (): void {
                app(RoomStateBroadcaster::class)->publish('room-id', 'participant_joined');

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // esperado
        }

        Event::assertNotDispatched(RoomStateChanged::class);
    }

    public function test_joining_a_room_dispatches_the_state_changed_event_for_that_room(): void
    {
        Event::fake([RoomStateChanged::class]);
        [$room, $token] = $this->roomWithInvitation();

        $this->actingAs(User::factory()->create())
            ->post(route('rooms.join', $token))
            ->assertRedirect(route('rooms.show', $room));

        Event::assertDispatchedTimes(RoomStateChanged::class, 1);
        Event::assertDispatched(
            RoomStateChanged::class,
            fn (RoomStateChanged $event) => $event->roomId === $room->id && $event->change === 'participant_joined',
        );
    }

    public function test_rejected_join_does_not_dispatch_the_event(): void
    {
        Event::fake([RoomStateChanged::class]);
        [$room, $token] = $this->roomWithInvitation();
        $room->invitations()->update(['revoked_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->post(route('rooms.join', $token))
            ->assertStatus(410);

        Event::assertNotDispatched(RoomStateChanged::class);
    }

    public function test_broadcast_failure_does_not_prevent_the_join(): void
    {
        $this->useBroadcasterThatFails();
        Log::spy();
        [$room, $token] = $this->roomWithInvitation();
        $guest = User::factory()->create();

        $this->actingAs($guest)
            ->post(route('rooms.join', $token))
            ->assertRedirect(route('rooms.show', $room));

        $this->assertDatabaseHas('room_participants', ['room_id' => $room->id, 'user_id' => $guest->id]);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Room state broadcast failed; polling remains available.', \Mockery::type('array'));
    }

    public function test_broadcast_failure_does_not_prevent_room_expiration(): void
    {
        $this->useBroadcasterThatFails();
        Log::spy();
        [$room] = $this->roomWithInvitation();

        $this->travel(5)->minutes();
        $this->artisan('rooms:expire-waiting')->assertSuccessful();

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'expired']);
    }

    /** @return array{0: GameRoom, 1: string} */
    private function roomWithInvitation(): array
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));

        return [$room, $token];
    }

    private function useBroadcasterThatFails(): void
    {
        Broadcast::extend('failing', fn () => new class extends Broadcaster
        {
            public function auth($request)
            {
                return null;
            }

            public function validAuthenticationResponse($request, $result)
            {
                return null;
            }

            public function broadcast(array $channels, $event, array $payload = [])
            {
                throw new BroadcastException('Reverb unavailable.');
            }
        });

        config([
            'broadcasting.default' => 'failing',
            'broadcasting.connections.failing' => ['driver' => 'failing'],
        ]);
    }
}
