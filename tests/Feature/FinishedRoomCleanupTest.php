<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\RoomInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Salas com partida concluída somem 2 minutos depois; o histórico de vitórias permanece. */
class FinishedRoomCleanupTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: GameRoom, 1: User, 2: User} */
    private function room(string $status, ?string $finishedAgo = null): array
    {
        [$a, $b] = User::factory()->count(2)->create();
        $room = GameRoom::create([
            'host_user_id' => $a->id, 'status' => $status, 'started_at' => $status === 'waiting' ? null : now()->subMinutes(20),
            'finished_at' => $finishedAgo ? now()->sub($finishedAgo) : null,
        ]);
        $w = $room->participants()->create(['user_id' => $a->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        $l = $room->participants()->create(['user_id' => $b->id, 'kind' => 'user', 'is_host' => false, 'seat' => 2, 'joined_at' => now()]);
        $player = fn ($seat) => ['seat' => $seat, 'hand' => ['git_add'], 'path' => [], 'step' => 0, 'blocked' => false, 'pending_discard' => 0];
        $state = ['deck' => [], 'discard' => [], 'history' => [], 'players' => [(string) $w->id => $player(1), (string) $l->id => $player(2)]];
        if ($status !== 'waiting') {
            GameMatch::create([
                'room_id' => $room->id, 'status' => $status === 'finished' ? 'finished' : 'active', 'current_seat' => 1, 'state' => $state,
                'winner_participant_id' => $status === 'finished' ? $w->id : null, 'finished_at' => $status === 'finished' ? $room->finished_at : null,
            ]);
        }

        return [$room, $a, $b];
    }

    public function test_room_is_deleted_two_minutes_after_the_win_and_history_is_kept(): void
    {
        [$room] = $this->room('finished', '3 minutes');
        $this->assertSame(2, DB::table('match_results')->count());

        $this->artisan('rooms:purge-finished')->expectsOutput('Salas excluídas: 1')->assertSuccessful();

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        $this->assertSame(0, GameMatch::count());
        $this->assertSame(0, DB::table('room_participants')->count());
        $this->assertSame(2, DB::table('match_results')->count()); // histórico preservado
        $this->assertSame(1, DB::table('match_results')->where('is_winner', true)->count());

        // Idempotente: rodar de novo não faz nada nem falha.
        $this->artisan('rooms:purge-finished')->expectsOutput('Salas excluídas: 0')->assertSuccessful();
    }

    public function test_recent_finished_active_and_waiting_rooms_are_never_touched(): void
    {
        [$recent] = $this->room('finished', '90 seconds');
        [$active] = $this->room('active');
        [$waiting] = $this->room('waiting');
        $active->forceFill(['started_at' => now()->subDay()])->save(); // partida longa continua viva

        $this->artisan('rooms:purge-finished')->expectsOutput('Salas excluídas: 0')->assertSuccessful();

        foreach ([$recent, $active, $waiting] as $room) {
            $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
        }

        $this->travel(40)->seconds(); // agora passou de 2 min para a primeira
        $this->artisan('rooms:purge-finished')->expectsOutput('Salas excluídas: 1')->assertSuccessful();
        $this->assertDatabaseMissing('game_rooms', ['id' => $recent->id]);
        $this->assertDatabaseHas('game_rooms', ['id' => $active->id]);
    }

    public function test_many_independent_rooms_are_handled_together(): void
    {
        $old = collect(range(1, 5))->map(fn () => $this->room('finished', '10 minutes')[0]);
        $fresh = collect(range(1, 3))->map(fn () => $this->room('finished', '10 seconds')[0]);

        $this->artisan('rooms:purge-finished')->expectsOutput('Salas excluídas: 5')->assertSuccessful();

        $old->each(fn ($r) => $this->assertDatabaseMissing('game_rooms', ['id' => $r->id]));
        $fresh->each(fn ($r) => $this->assertDatabaseHas('game_rooms', ['id' => $r->id]));
        $this->assertSame(16, DB::table('match_results')->count());
    }

    public function test_dashboard_hides_finished_rooms_at_once_and_purges_overdue_ones_without_the_scheduler(): void
    {
        [$justFinished, $a] = $this->room('finished', '10 seconds');
        [$overdue] = $this->room('finished', '5 minutes');
        $overdue->participants()->where('seat', 1)->update(['user_id' => $a->id]); // mesma pessoa nas duas

        $this->actingAs($a)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('rooms', 0));

        $this->assertDatabaseMissing('game_rooms', ['id' => $overdue->id]); // limpeza sob demanda
        $this->assertDatabaseHas('game_rooms', ['id' => $justFinished->id]); // ainda dentro dos 2 min
    }

    public function test_opening_an_overdue_room_sends_the_player_to_the_dashboard_with_a_notice(): void
    {
        [$room, $a] = $this->room('finished', '5 minutes');

        $this->actingAs($a)->get(route('games.show', $room))->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        $this->actingAs($a)->get(route('games.show', $room))->assertNotFound();
    }

    public function test_finished_room_exposes_the_cleanup_deadline_to_the_victory_screen(): void
    {
        [$room, , $b] = $this->room('finished', '30 seconds');

        $this->actingAs($b)->get(route('games.show', $room))->assertInertia(fn (Assert $page) => $page
            ->component('Games/Show')->has('game.cleanup_at')->where('game.status', 'finished'));
    }

    public function test_nobody_can_join_a_finished_or_deleted_room_with_the_old_code(): void
    {
        [$room] = $this->room('finished', '10 seconds');
        $code = 'AB2CD';
        RoomInvitation::create(['room_id' => $room->id, 'token_hash' => hash('sha256', $code), 'expires_at' => now()->addHour()]);
        $guest = User::factory()->create();

        $this->actingAs($guest)->get(route('invites.open', $code))->assertStatus(409);
        $this->actingAs($guest)->post(route('rooms.join', $code))->assertStatus(409);

        $room->update(['finished_at' => now()->subMinutes(5)]);
        $this->artisan('rooms:purge-finished')->assertSuccessful();
        $this->actingAs($guest)->get(route('invites.open', $code))->assertNotFound();
        $this->actingAs($guest)->post(route('rooms.join', $code))->assertNotFound();
    }

    public function test_manual_deletion_follows_ownership_and_never_touches_running_matches(): void
    {
        [$finished, $host, $other] = $this->room('finished', '30 seconds');
        $this->actingAs($other)->delete(route('rooms.cancel', $finished))->assertForbidden();
        $this->assertDatabaseHas('game_rooms', ['id' => $finished->id]);

        $this->actingAs($host)->delete(route('rooms.cancel', $finished))->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('game_rooms', ['id' => $finished->id]);
        $this->assertSame(2, DB::table('match_results')->count()); // vitória segue no ranking

        [$active, $h2] = $this->room('active');
        $this->actingAs($h2)->delete(route('rooms.cancel', $active))->assertStatus(409);
        $this->assertDatabaseHas('game_rooms', ['id' => $active->id]);

        [$waiting, $h3] = $this->room('waiting');
        $this->actingAs($h3)->delete(route('rooms.cancel', $waiting))->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('game_rooms', ['id' => $waiting->id]);
    }

    public function test_room_without_participants_is_still_cleaned(): void
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'finished', 'finished_at' => now()->subMinutes(4)]);

        $this->artisan('rooms:purge-finished')->expectsOutput('Salas excluídas: 1')->assertSuccessful();
        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
    }
}
