<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\User;
use App\Events\RoomStateChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_private_room_with_hashed_invitation(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $response->assertRedirect(route('rooms.show', $room));
        $this->assertDatabaseHas('room_participants', ['room_id'=>$room->id,'user_id'=>$user->id,'seat'=>1]);
        $invitation = $room->invitations()->firstOrFail();
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertNotEmpty(session('invite_url'));
        $this->assertStringNotContainsString($invitation->token_hash, session('invite_url'));
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $this->assertMatchesRegularExpression('/^(?:[A-HJ-NP-Z][2-9]){2}[A-HJ-NP-Z]$|^(?:[2-9][A-HJ-NP-Z]){2}[2-9]$/', $code);
    }

    public function test_valid_invitation_allows_only_four_unique_participants(): void
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));

        foreach (User::factory()->count(3)->create() as $player) {
            $this->actingAs($player)->post(route('rooms.join', $token))->assertRedirect(route('rooms.show', $room));
        }
        $this->assertSame(4, $room->participants()->count());
        $fifth = User::factory()->create();
        $this->actingAs($fifth)->post(route('rooms.join', $token))->assertStatus(409);
        $this->assertSame(4, $room->participants()->count());
    }

    public function test_revoked_invitation_cannot_be_used(): void
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $this->actingAs($host)->delete(route('rooms.invite.revoke', $room));
        $guest = User::factory()->create();
        $this->actingAs($guest)->post(route('rooms.join', $token))->assertStatus(410);
    }

    public function test_room_creator_can_delete_their_empty_waiting_room(): void
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();

        $this->actingAs($host)
            ->delete(route('rooms.cancel', $room))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        $this->assertDatabaseMissing('room_participants', ['room_id' => $room->id]);
        $this->assertDatabaseMissing('room_invitations', ['room_id' => $room->id]);
    }

    public function test_user_cannot_delete_another_users_room(): void
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();

        $this->actingAs(User::factory()->create())
            ->delete(route('rooms.cancel', $room))
            ->assertForbidden();

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_creator_cannot_delete_a_room_after_another_player_joins(): void
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $guest = User::factory()->create();

        $this->actingAs($guest)->post(route('rooms.join', $token));

        $this->actingAs($host)
            ->delete(route('rooms.cancel', $room))
            ->assertStatus(409);

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'waiting']);
        $this->assertDatabaseHas('room_participants', ['room_id' => $room->id, 'user_id' => $guest->id]);
    }

    public function test_waiting_room_expires_after_five_minutes_and_revokes_its_invitation(): void
    {
        Event::fake([RoomStateChanged::class]);
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $invitation = $room->invitations()->firstOrFail();

        $this->travel(5)->minutes();
        $this->artisan('rooms:expire-waiting')->assertSuccessful();

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'expired']);
        $this->assertNotNull($invitation->fresh()->revoked_at);
        Event::assertDispatched(RoomStateChanged::class, fn ($event) => $event->roomId === $room->id && $event->change === 'expired');
    }

    public function test_player_cannot_join_a_room_past_its_server_side_deadline(): void
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));

        $this->travel(5)->minutes();

        $this->actingAs(User::factory()->create())
            ->post(route('rooms.join', $token))
            ->assertGone();

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'expired']);
    }

    public function test_room_with_an_additional_player_does_not_expire(): void
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $this->actingAs(User::factory()->create())->post(route('rooms.join', $token));

        $this->travel(5)->minutes();
        $this->artisan('rooms:expire-waiting')->assertSuccessful();

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'waiting']);
    }

    public function test_room_page_includes_server_generated_expiration_time(): void
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $expectedExpiry = $room->created_at->copy()->addMinutes(5)->toIso8601String();

        $this->actingAs($host)
            ->get(route('rooms.show', $room))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Rooms/Show')
                ->where('expiresAt', $expectedExpiry)
                ->where('room.status', 'waiting'));
    }

    public function test_opening_a_room_after_its_deadline_displays_it_as_expired(): void
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();

        $this->travel(5)->minutes();

        $this->actingAs($host)
            ->get(route('rooms.show', $room))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('room.status', 'expired'));
    }
}
