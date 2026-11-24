<?php

namespace Tests\Feature;

use App\Events\RoomStateChanged;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** A vitória é decidida e registrada pelo servidor, uma vez, e chega igual a todos os jogadores. */
class VictorySyncTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: GameRoom, 1: \Illuminate\Support\Collection, 2: GameMatch} */
    private function startedRoom(): array
    {
        $users = User::factory()->count(4)->create(['matches_won' => 4, 'matches_played' => 9, 'matches_lost' => 5])->values();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'waiting']);
        foreach ($users as $i => $user) {
            $room->participants()->create(['user_id' => $user->id, 'kind' => 'user', 'is_host' => $i === 0, 'seat' => $i + 1, 'joined_at' => now()]);
        }
        $this->actingAs($users[0])->post(route('games.start', $room));
        $match = GameMatch::firstOrFail();

        return [$room, $users, $match];
    }

    private function prepareWinningMove(GameMatch $match, int $participantId): void
    {
        $state = $match->state;
        $state['players'][(string) $participantId]['step'] = 17;
        $state['players'][(string) $participantId]['hand'] = ['pull_request'];
        $state['players'][(string) $participantId]['path'] = array_slice(GameEngine::REQUIRED, 0, 17);
        $state['players'][(string) $participantId]['pending_discard'] = 0;
        $match->forceFill(['state' => $state, 'current_seat' => 1])->save();
    }

    private function win(GameRoom $room, GameMatch $match, User $user, ?string $uuid = null)
    {
        return $this->actingAs($user)->post(route('games.action', $room), [
            'action_uuid' => $uuid ?? (string) Str::uuid(), 'action' => 'play_card', 'card' => 'pull_request', 'version' => $match->fresh()->version,
        ]);
    }

    public function test_every_participant_receives_the_same_final_state_and_winner(): void
    {
        [$room, $users, $match] = $this->startedRoom();
        $winner = $room->participants()->where('seat', 1)->firstOrFail();
        $this->prepareWinningMove($match, $winner->id);

        $this->win($room, $match, $users[0]);

        foreach ($users as $user) { // quem venceu e quem NÃO jogou a última carta
            $this->actingAs($user)->get(route('games.show', $room))->assertInertia(fn (Assert $page) => $page
                ->component('Games/Show')
                ->where('game.status', 'finished')
                ->where('game.winner_participant_id', $winner->id)
                ->where('game.winner_wins', 5) // 4 anteriores + esta
                ->has('game.cleanup_at')
                ->has('participants', 4));
        }
    }

    public function test_the_win_is_broadcast_to_the_room_so_everybody_updates(): void
    {
        [$room, $users, $match] = $this->startedRoom();
        $this->prepareWinningMove($match, $room->participants()->where('seat', 1)->firstOrFail()->id);
        Event::fake([RoomStateChanged::class]);

        $this->win($room, $match, $users[0]);

        Event::assertDispatched(RoomStateChanged::class, fn ($e) => $e->roomId === $room->id && $e->change === 'game_updated');
    }

    public function test_the_win_is_recorded_exactly_once_even_with_repeated_requests(): void
    {
        [$room, $users, $match] = $this->startedRoom();
        $winner = $room->participants()->where('seat', 1)->firstOrFail();
        $this->prepareWinningMove($match, $winner->id);
        $uuid = (string) Str::uuid();

        $this->win($room, $match, $users[0], $uuid)->assertRedirect();
        $this->win($room, $match, $users[0], $uuid); // reenvio do mesmo clique (rede lenta, reconexão)
        $this->actingAs($users[0])->post(route('games.action', $room), [ // outra tentativa depois do fim
            'action_uuid' => (string) Str::uuid(), 'action' => 'play_card', 'card' => 'pull_request', 'version' => $match->fresh()->version,
        ])->assertSessionHasErrors();

        $this->assertSame(5, $users[0]->fresh()->matches_won);   // contou uma vez só
        $this->assertSame(10, $users[0]->fresh()->matches_played);
        $this->assertSame(6, $users[1]->fresh()->matches_lost);
        $this->assertSame(4, DB::table('match_results')->count());   // um resultado por conta
        $this->assertSame(1, DB::table('match_results')->where('is_winner', true)->count());
        $this->assertSame(1, GameMatch::where('status', 'finished')->count());
    }

    public function test_a_player_who_was_offline_and_reconnects_recovers_the_final_state(): void
    {
        [$room, $users, $match] = $this->startedRoom();
        $winner = $room->participants()->where('seat', 1)->firstOrFail();
        $this->prepareWinningMove($match, $winner->id);
        $this->win($room, $match, $users[0]);

        // Quem ficou desconectado perdeu o evento: ao reabrir (ou no polling), recebe o estado final.
        $this->actingAs($users[3])->get(route('games.show', $room))->assertInertia(fn (Assert $page) => $page
            ->where('game.status', 'finished')->where('game.winner_participant_id', $winner->id));
        // E recarregar várias vezes continua mostrando o MESMO resultado, sem contar nada de novo.
        $this->actingAs($users[3])->get(route('games.show', $room))->assertOk();
        $this->assertSame(5, $users[0]->fresh()->matches_won);
    }

    public function test_outsiders_cannot_see_or_change_the_match(): void
    {
        [$room, , $match] = $this->startedRoom();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('games.show', $room))->assertNotFound();
        $this->win($room, $match, $stranger)->assertNotFound();
    }
}
