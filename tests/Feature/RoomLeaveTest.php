<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sair de uma sala: some se só restarem bots; continua se houver ao menos um humano. */
class RoomLeaveTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: GameRoom, 1: list<User>} sala com `$humans` pessoas (a primeira é a anfitriã) e o resto de bots */
    private function room(int $humans, bool $started = false): array
    {
        $users = User::factory()->count($humans)->create()->values()->all();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'waiting', 'bot_slots' => 4 - $humans]);
        foreach ($users as $i => $user) {
            $room->participants()->create(['user_id' => $user->id, 'kind' => 'user', 'is_host' => $i === 0, 'seat' => $i + 1, 'joined_at' => now()->addSeconds($i)]);
        }
        foreach (array_slice(array_column(config('bots.catalog'), 'key'), 0, 4 - $humans) as $i => $key) {
            $room->participants()->create(['kind' => 'bot', 'bot_key' => $key, 'is_host' => false, 'seat' => $humans + $i + 1, 'joined_at' => now()]);
        }
        if ($started) {
            $this->actingAs($users[0])->post(route('games.start', $room))->assertRedirect();
        }

        return [$room->fresh(), $users];
    }

    public function test_the_host_leaving_a_lobby_with_only_bots_left_destroys_the_room(): void
    {
        [$room, $users] = $this->room(1);

        $this->actingAs($users[0])->delete(route('rooms.leave', $room))->assertRedirect(route('dashboard'))->assertSessionHas('toast.title', 'A sala foi encerrada');

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        $this->assertDatabaseCount('room_participants', 0);
    }

    public function test_the_host_leaving_with_two_bots_and_one_other_player_keeps_the_room_and_passes_the_host(): void
    {
        [$room, $users] = $this->room(2);

        $this->actingAs($users[0])->delete(route('rooms.leave', $room))->assertRedirect(route('dashboard'));

        $room->refresh();
        $this->assertSame($users[1]->id, $room->host_user_id);
        $this->assertNull($room->host_guest_id);
        $this->assertSame(3, $room->participants()->count());
        $this->assertTrue((bool) $room->participants()->where('user_id', $users[1]->id)->value('is_host'));
        $this->assertFalse($room->participants()->where('user_id', $users[0]->id)->exists());
        $this->assertTrue($room->isHostedBy($users[1]));
    }

    public function test_the_host_leaving_with_one_bot_and_two_other_players_keeps_the_room(): void
    {
        [$room, $users] = $this->room(3);

        $this->actingAs($users[0])->delete(route('rooms.leave', $room))->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'host_user_id' => $users[1]->id]);
        $this->assertSame(2, $room->participants()->where('kind', 'user')->count());
    }

    public function test_the_host_leaving_a_running_match_with_three_bots_destroys_room_and_match(): void
    {
        [$room, $users] = $this->room(1, started: true);
        $this->assertSame(1, GameMatch::count());

        $this->actingAs($users[0])->delete(route('rooms.leave', $room))->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        $this->assertSame(0, GameMatch::count());
    }

    public function test_leaving_a_running_match_hands_the_seat_to_a_bot_and_the_match_goes_on(): void
    {
        [$room, $users] = $this->room(2, started: true);
        $leaving = $room->participants()->where('user_id', $users[1]->id)->firstOrFail();

        $this->actingAs($users[1])->delete(route('rooms.leave', $room))->assertRedirect(route('dashboard'));

        $leaving->refresh();
        $this->assertSame('bot', $leaving->kind);
        $this->assertNull($leaving->user_id);
        $this->assertSame(4, $room->participants()->count());
        $this->assertSame(1, $room->participants()->where('kind', '!=', 'bot')->count());
        $match = GameMatch::firstOrFail();
        $this->assertSame('active', $match->status);
        $this->assertTrue(collect($match->state['history'])->contains(fn ($item) => str_contains($item['message'], 'abandonou')));
    }

    public function test_when_the_last_two_humans_both_leave_the_room_disappears(): void
    {
        [$room, $users] = $this->room(2, started: true);

        $this->actingAs($users[1])->delete(route('rooms.leave', $room));
        $this->actingAs($users[0])->delete(route('rooms.leave', $room))->assertSessionHas('toast.title', 'A sala foi encerrada');

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
    }

    public function test_idle_player_replaced_by_a_bot_closes_the_room_when_nobody_human_is_left(): void
    {
        [$room, $users] = $this->room(1, started: true);
        GameMatch::firstOrFail()->forceFill(['current_seat' => 1])->save();

        $this->travel(config('game.turn_timeout_seconds') + 5)->seconds();
        $this->actingAs($users[0])->get(route('games.show', $room))->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
    }

    public function test_strangers_cannot_leave_a_room_they_are_not_in(): void
    {
        [$room] = $this->room(2);

        $this->actingAs(User::factory()->create())->delete(route('rooms.leave', $room))->assertNotFound();
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_a_finished_match_keeps_its_history_when_someone_leaves(): void
    {
        [$room, $users] = $this->room(2, started: true);
        $room->forceFill(['status' => 'finished'])->save();

        $this->actingAs($users[0])->delete(route('rooms.leave', $room))->assertRedirect(route('dashboard'));

        $this->assertSame(4, $room->participants()->count());
        $this->assertSame(1, GameMatch::count());
    }
}
