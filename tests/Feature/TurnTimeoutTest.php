<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\RoomParticipant;
use App\Models\User;
use App\Services\GameEngine;
use App\Services\GameInactivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Vez de 25 s, pulo por tempo, 3 timeouts seguidos = saída da sala, e anfitrião sempre humano. */
class TurnTimeoutTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: GameRoom, 1: GameMatch, 2: \Illuminate\Support\Collection} participantes por assento */
    private function table(int $humans = 4): array
    {
        $users = User::factory()->count($humans)->create()->values();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'waiting']);
        for ($seat = 1; $seat <= 4; $seat++) {
            $human = $seat <= $humans;
            $room->participants()->create([
                'user_id' => $human ? $users[$seat - 1]->id : null,
                'kind' => $human ? 'user' : 'bot',
                'bot_key' => $human ? null : array_keys(config('bots.catalog'))[$seat] ?? 'x'.$seat,
                'is_host' => $seat === 1,
                'seat' => $seat,
                'joined_at' => now()->subMinutes(10 - $seat), // assento 1 entrou primeiro
            ]);
        }
        $match = app(GameEngine::class)->create($room);
        $room->update(['status' => 'active', 'started_at' => now()]);
        $match->forceFill(['current_seat' => 1, 'turn_started_at' => now()])->save();

        return [$room, $match, $room->participants()->orderBy('seat')->get()->keyBy('seat')];
    }

    private function sweep(GameRoom $room): bool
    {
        return app(GameInactivityService::class)->sweep($room->fresh());
    }

    public function test_the_turn_limit_is_under_thirty_seconds_and_centralized(): void
    {
        $this->assertLessThan(30, config('game.turn_timeout_seconds'));
        $this->assertSame(25, config('game.turn_timeout_seconds'));
        $this->assertSame(3, config('game.max_consecutive_timeouts'));
    }

    public function test_within_the_deadline_nothing_happens(): void
    {
        [$room, $match] = $this->table();
        $this->travel(20)->seconds();

        $this->assertFalse($this->sweep($room));
        $this->assertSame(1, (int) $match->fresh()->current_seat);
    }

    public function test_a_timeout_skips_the_turn_and_counts_once_even_if_swept_twice(): void
    {
        [$room, $match, $seats] = $this->table();
        $versionBefore = $match->version;
        $this->travel(26)->seconds();

        $this->assertTrue($this->sweep($room));
        $this->assertFalse($this->sweep($room), 'segunda verificação no mesmo prazo não conta de novo');

        $fresh = $match->fresh();
        $this->assertSame(2, (int) $fresh->current_seat);
        $this->assertSame(1, (int) $seats[1]->fresh()->timeout_streak);
        $this->assertGreaterThan($versionBefore, $fresh->version);
        $this->assertSame('user', $seats[1]->fresh()->kind, 'ainda não foi removido');
    }

    public function test_the_third_consecutive_timeout_removes_the_player_and_the_table_goes_on(): void
    {
        [$room, $match, $seats] = $this->table();
        $seats[1]->forceFill(['timeout_streak' => 2])->save();
        $this->travel(26)->seconds();

        $this->assertTrue($this->sweep($room));

        $this->assertSame('bot', $seats[1]->fresh()->kind);
        $this->assertNull($seats[1]->fresh()->user_id);
        $this->assertNotNull(GameRoom::find($room->id), 'há outros humanos: a sala continua');
        $this->assertSame('active', $match->fresh()->status);
    }

    public function test_a_valid_play_resets_the_streak(): void
    {
        [$room, $match, $seats] = $this->table();
        $seats[1]->forceFill(['timeout_streak' => 2])->save();
        $state = $match->state;
        $state['players'][(string) $seats[1]->id]['hand'] = ['git_login', 'git_clone', 'git_add', 'git_push'];
        $match->forceFill(['state' => $state])->save();

        app(GameEngine::class)->act($match->fresh(), $seats[1]->fresh(), 'play_card', ['card' => 'git_login'], (string) Str::uuid());

        $this->assertSame(0, (int) $seats[1]->fresh()->timeout_streak);
    }

    public function test_a_late_play_is_refused_by_the_server_clock(): void
    {
        [$room, $match, $seats] = $this->table();
        $this->travel(40)->seconds();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(GameEngine::class)->act($match->fresh(), $seats[1]->fresh(), 'exchange', ['card' => $match->state['players'][(string) $seats[1]->id]['hand'][0]], (string) Str::uuid());
    }

    public function test_removing_the_host_hands_the_room_to_the_next_human_never_a_bot(): void
    {
        [$room, $match, $seats] = $this->table(3); // assentos 1-3 humanos, 4 é bot
        $seats[1]->forceFill(['timeout_streak' => 2])->save();
        $this->travel(26)->seconds();

        $this->sweep($room);

        $room = $room->fresh();
        $this->assertSame($seats[2]->user_id, $room->host_user_id, 'quem entrou logo depois assume');
        $this->assertTrue((bool) $seats[2]->fresh()->is_host);
        $this->assertSame(1, $room->participants()->where('is_host', true)->count());
        $this->assertFalse((bool) $room->participants()->where('kind', 'bot')->where('is_host', true)->exists());
    }

    public function test_a_room_left_only_with_bots_is_closed(): void
    {
        [$room, $match, $seats] = $this->table(1); // uma pessoa e três bots
        $seats[1]->forceFill(['timeout_streak' => 2])->save();
        $this->travel(26)->seconds();

        $this->sweep($room);

        $this->assertNull(GameRoom::find($room->id), 'sem humanos a sala some');
        $this->assertSame(0, GameMatch::where('room_id', $room->id)->count());
    }

    public function test_a_bots_turn_is_never_swept(): void
    {
        [$room, $match] = $this->table(1);
        $match->forceFill(['current_seat' => 2])->save();
        $this->travel(120)->seconds();

        $this->assertFalse($this->sweep($room));
    }

    public function test_the_displayed_time_comes_from_the_server_and_is_bounded(): void
    {
        [$room, $match, $seats] = $this->table();
        $user = $seats[1]->user;
        $this->travel(10)->seconds();

        $left = null;
        $this->actingAs($user)->get(route('games.show', $room))
            ->assertInertia(function ($page) use (&$left) {
                $left = $page->toArray()['props']['game']['turn_seconds_left'];
            });

        $this->assertGreaterThanOrEqual(13, $left);
        $this->assertLessThanOrEqual(25, $left);
    }
}
