<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
