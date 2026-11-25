<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GameEngine;
use App\Services\GameInactivityService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Timeouts e bots resolvidos pelo servidor mesmo com todas as páginas fechadas; prazo só para humanos. */
class AutoSweepTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: GameRoom, 1: GameMatch, 2: \Illuminate\Support\Collection} */
    private function table(int $humans, int $currentSeat = 1): array
    {
        $users = User::factory()->count(max(1, $humans))->create()->values();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'waiting']);
        for ($seat = 1; $seat <= 4; $seat++) {
            $human = $seat <= $humans;
            $room->participants()->create([
                'user_id' => $human ? $users[$seat - 1]->id : null,
                'kind' => $human ? 'user' : 'bot',
                'bot_key' => $human ? null : (array_keys(config('bots.catalog'))[$seat] ?? 'x'.$seat),
                'is_host' => $seat === 1,
                'seat' => $seat,
                'joined_at' => now()->subMinutes(10 - $seat),
            ]);
        }
        $match = app(GameEngine::class)->create($room);
        $room->update(['status' => 'active', 'started_at' => now()]);
        $match->forceFill(['current_seat' => $currentSeat, 'turn_started_at' => now()])->save();

        return [$room, $match, $room->participants()->orderBy('seat')->get()->keyBy('seat')];
    }

    private function sweepAll(): array
    {
        return app(GameInactivityService::class)->sweepStalled();
    }

    // ---------------------------------------------------------------- prazo: humano x bot

    public function test_a_human_inside_the_deadline_plays_normally(): void
    {
        [, $match, $seats] = $this->table(4);
        $this->travel(10)->seconds();

        app(GameEngine::class)->act($match->fresh(), $seats[1]->fresh(), 'exchange', ['card' => $match->state['players'][(string) $seats[1]->id]['hand'][0]], (string) Str::uuid());

        $this->assertSame(2, (int) $match->fresh()->current_seat);
    }

    public function test_a_human_outside_the_deadline_is_refused(): void
    {
        [, $match, $seats] = $this->table(4);
        $this->travel(40)->seconds();

        $this->expectException(ValidationException::class);
        app(GameEngine::class)->act($match->fresh(), $seats[1]->fresh(), 'exchange', ['card' => $match->state['players'][(string) $seats[1]->id]['hand'][0]], (string) Str::uuid());
    }

    public function test_a_bot_is_never_refused_by_the_human_deadline(): void
    {
        [$room, $match] = $this->table(1, currentSeat: 2);
        $this->travel(300)->seconds(); // muito além do prazo de qualquer humano

        $played = app(GameEngine::class)->botStep($match->fresh(), $room->fresh());

        $this->assertTrue($played, 'o bot joga mesmo com o relógio da vez vencido');
        $this->assertSame(3, (int) $match->fresh()->current_seat);
    }

    public function test_the_participant_type_comes_from_the_server_not_from_the_client(): void
    {
        [$room, $match, $seats] = $this->table(4);
        $this->travel(40)->seconds();

        // O cliente tenta se passar por bot nos campos da requisição: o tipo vem do registro do servidor.
        $this->actingAs($seats[1]->user)->from(route('games.show', $room))->post(route('games.action', $room), [
            'action_uuid' => (string) Str::uuid(), 'action' => 'exchange', 'version' => $match->version,
            'card' => $match->state['players'][(string) $seats[1]->id]['hand'][0],
            'kind' => 'bot', 'is_bot' => true, 'participant_id' => 999,
        ])->assertSessionHasErrors('turn');

        $this->assertSame(1, (int) $match->fresh()->current_seat, 'a jogada não foi aplicada');
        $this->assertSame('user', $seats[1]->fresh()->kind);
    }

    public function test_a_stale_bot_request_is_ignored_by_version(): void
    {
        [$room, $match, $seats] = $this->table(1, currentSeat: 2);
        $staleVersion = $match->version - 1;

        $this->actingAs($seats[1]->user)->post(route('games.bots', $room), ['version' => $staleVersion]);

        $this->assertSame(2, (int) $match->fresh()->current_seat, 'versão antiga não faz o bot jogar');
    }

    // ---------------------------------------------------------------- varredura sem página aberta

    public function test_the_sweep_command_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'games:sweep') || $e->description === 'games:sweep');

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_a_human_timeout_is_resolved_with_every_page_closed(): void
    {
        [, $match, $seats] = $this->table(4);
        $this->travel(26)->seconds();

        $result = $this->sweepAll();

        $this->assertSame(1, $result['timeouts']);
        $this->assertSame(2, (int) $match->fresh()->current_seat);
        $this->assertSame(1, (int) $seats[1]->fresh()->timeout_streak);
    }

    public function test_running_the_sweep_twice_does_not_count_twice(): void
    {
        [, , $seats] = $this->table(4);
        $this->travel(26)->seconds();

        $this->sweepAll();
        $second = $this->sweepAll();

        $this->assertSame(0, $second['timeouts']);
        $this->assertSame(1, (int) $seats[1]->fresh()->timeout_streak);
    }

    public function test_the_sweep_does_nothing_inside_the_deadline_and_ignores_finished_matches(): void
    {
        [, $match] = $this->table(4);
        $this->travel(10)->seconds();
        $inside = $this->sweepAll();
        $this->assertSame(0, $inside['timeouts'] + $inside['bot_steps'], 'vez de humano dentro do prazo não é tocada');

        $match->forceFill(['status' => 'finished'])->save();
        $this->travel(120)->seconds();
        $this->assertSame(0, $this->sweepAll()['checked']);
    }

    public function test_a_stalled_bot_turn_is_played_by_the_server_until_a_human_is_up(): void
    {
        [, $match] = $this->table(1, currentSeat: 2); // vez do bot do assento 2; o humano é o assento 1
        $this->travel(config('game.bot_stall_seconds') + 2)->seconds();

        $result = $this->sweepAll();

        $this->assertGreaterThan(0, $result['bot_steps']);
        $this->assertSame(1, (int) $match->fresh()->current_seat, 'parou quando chegou a vez do humano');
    }

    public function test_a_fresh_bot_turn_is_left_alone_for_the_client_to_pace(): void
    {
        [, $match] = $this->table(1, currentSeat: 2);
        $this->travel(2)->seconds();

        $this->assertSame(0, $this->sweepAll()['bot_steps']);
        $this->assertSame(2, (int) $match->fresh()->current_seat);
    }

    public function test_the_sweep_leaves_cloudflare_matches_to_their_own_engine(): void
    {
        [, $match] = $this->table(4);
        $state = $match->state;
        $state['engine'] = 'cloudflare';
        $match->forceFill(['state' => $state])->save();
        $this->travel(60)->seconds();

        $this->assertSame(0, $this->sweepAll()['checked']);
    }

    public function test_three_closed_page_timeouts_remove_the_player_without_anyone_online(): void
    {
        [$room, $match, $seats] = $this->table(2);
        $seats[1]->forceFill(['timeout_streak' => 2])->save();
        $this->travel(26)->seconds();

        $this->sweepAll();

        $this->assertSame('bot', $seats[1]->fresh()->kind);
        $this->assertTrue((bool) $seats[2]->fresh()->is_host, 'o anfitrião passou ao outro humano');
        $this->assertNotNull(GameRoom::find($room->id));
        $this->assertSame(1, $room->participants()->where('is_host', true)->count());
    }
}
