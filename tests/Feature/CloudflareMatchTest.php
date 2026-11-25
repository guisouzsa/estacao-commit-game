<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\CloudflareMatchGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Partidas no Durable Object (GAME_ENGINE=cloudflare). O Worker é simulado com Http::fake. */
class CloudflareMatchTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'realtime.driver' => 'cloudflare', 'realtime.game_engine' => 'cloudflare', 'realtime.engine_parity_ack' => CloudflareMatchGateway::PARITY_VERSION,
            'realtime.worker_url' => 'https://worker.example',
            'realtime.token_secret' => 'tok', 'realtime.notify_secret' => 'notify', 'realtime.callback_secret' => 'callback',
        ]);
    }

    private function humanRoom(): array
    {
        $users = User::factory()->count(4)->create()->values();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'waiting']);
        foreach ($users as $i => $user) {
            $room->participants()->create(['user_id' => $user->id, 'kind' => 'user', 'is_host' => $i === 0, 'seat' => $i + 1, 'joined_at' => now()]);
        }

        return [$room, $users];
    }

    private function remoteView(GameRoom $room, int $version = 5): array
    {
        return [
            'game' => ['status' => 'active', 'current_seat' => 1, 'turn_number' => 3, 'version' => $version, 'winner_participant_id' => null,
                'deck_count' => 50, 'discard_count' => 2, 'turn_seconds_left' => 90, 'history' => [['id' => 'h1', 'message' => 'x', 'turn' => 1]]],
            'participants' => $room->participants->map(fn ($p) => ['id' => $p->id, 'seat' => $p->seat, 'kind' => $p->kind, 'bot_key' => null,
                'hand_count' => 4, 'path' => [], 'step' => 0, 'blocked' => false, 'pending_discard' => 0])->all(),
            'hand' => ['git_login', 'git_add', 'git_add', 'git_commit'],
            'requiredCard' => 'git_login',
        ];
    }

    private function sign(string $body, string $secret = 'callback', ?int $ts = null, string $nonce = null): array
    {
        $ts ??= time();
        $nonce ??= bin2hex(random_bytes(8));

        return ['X-Timestamp' => (string) $ts, 'X-Nonce' => $nonce,
            'X-Signature' => hash_hmac('sha256', "$ts.$nonce.".hash('sha256', $body), $secret)];
    }

    private function postCallback(array $event, array $headers = null)
    {
        $body = json_encode($event);

        return $this->call('POST', '/realtime/callback', [], [], [], $this->server($headers ?? $this->sign($body)), $body);
    }

    private function server(array $headers): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }

        return $server;
    }

    public function test_default_engine_never_calls_the_worker(): void
    {
        Http::fake();
        [$room, $users] = $this->humanRoom();

        $this->actingAs($users[0])->post(route('games.start', $room))->assertRedirect(route('games.show', $room));

        Http::assertNothingSent();
        $this->assertCount(67, GameMatch::firstOrFail()->state['deck']);
    }

    public function test_start_creates_a_light_record_and_sends_a_signed_start_to_the_worker(): void
    {
        $this->enable();
        Http::fake(['worker.example/*' => Http::response(['created' => true, 'version' => 1])]);
        [$room, $users] = $this->humanRoom();

        $this->actingAs($users[0])->post(route('games.start', $room))->assertRedirect(route('games.show', $room));

        $match = GameMatch::firstOrFail();
        $this->assertSame(['engine' => 'cloudflare'], $match->state);
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'active']);
        Http::assertSent(function (Request $r) use ($room): bool {
            $d = $r->data();

            return $r->url() === "https://worker.example/match/{$room->id}/start"
                && count($d['participants']) === 4 && $d['room_id'] === $room->id
                && $r->header('X-Signature')[0] === hash_hmac('sha256', $r->header('X-Timestamp')[0].'.'.$r->header('X-Nonce')[0].'.'.hash('sha256', $r->body()), 'notify');
        });
    }

    public function test_if_the_worker_is_down_the_room_is_not_left_started(): void
    {
        $this->enable();
        Http::fake(['worker.example/*' => Http::response('boom', 500)]);
        [$room, $users] = $this->humanRoom();

        $this->actingAs($users[0])->post(route('games.start', $room));

        $this->assertDatabaseCount('game_matches', 0);
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'waiting']);
    }

    public function test_show_renders_the_same_page_props_from_the_worker_view(): void
    {
        $this->enable();
        [$room, $users] = $this->humanRoom();
        GameMatch::create(['room_id' => $room->id, 'status' => 'active', 'current_seat' => 1, 'turn_number' => 1, 'version' => 1, 'state' => ['engine' => 'cloudflare']]);
        Http::fake(['worker.example/*' => Http::response($this->remoteView($room->load('participants')))]);

        $this->actingAs($users[1])->get(route('games.show', $room))->assertInertia(fn (Assert $page) => $page
            ->component('Games/Show')
            ->has('hand', 4)->has('participants', 4)
            ->where('game.version', 5)->where('game.turn_seconds_left', 90)
            ->where('requiredCard', 'git_login')->missing('game.state'));
        $this->actingAs(User::factory()->create())->get(route('games.show', $room))->assertNotFound();
    }

    public function test_actions_are_forwarded_and_worker_errors_keep_the_php_behaviour(): void
    {
        $this->enable();
        [$room, $users] = $this->humanRoom();
        GameMatch::create(['room_id' => $room->id, 'status' => 'active', 'current_seat' => 1, 'turn_number' => 1, 'version' => 1, 'state' => ['engine' => 'cloudflare']]);
        $payload = ['action_uuid' => (string) \Illuminate\Support\Str::uuid(), 'action' => 'exchange', 'card' => 'git_add', 'version' => 5];

        Http::fake(['worker.example/*' => Http::sequence()
            ->push(['version' => 6, 'status' => 'active'])
            ->push(['errors' => ['turn' => 'É a vez de outro jogador.']], 422)
            ->push(['error' => 'conflict'], 409)]);
        $this->actingAs($users[0])->post(route('games.action', $room), $payload)->assertRedirect();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), "/match/{$room->id}/act") && $r->data()['participant_id'] === $room->participants()->first()->id && $r->data()['version'] === 5);

        $this->actingAs($users[1])->post(route('games.action', $room), $payload)->assertSessionHasErrors('turn');

        $this->actingAs($users[0])->post(route('games.action', $room), $payload)->assertStatus(409);

        $this->actingAs(User::factory()->create())->post(route('games.action', $room), $payload)->assertNotFound();
    }

    public function test_finished_callback_records_the_result_once_and_updates_the_scoreboard(): void
    {
        $this->enable();
        [$room, $users] = $this->humanRoom();
        $room->forceFill(['status' => 'active', 'started_at' => now()])->save();
        GameMatch::create(['room_id' => $room->id, 'status' => 'active', 'current_seat' => 1, 'turn_number' => 1, 'version' => 1, 'state' => ['engine' => 'cloudflare']]);
        $winner = $room->participants()->where('seat', 2)->first();
        $event = ['type' => 'match_finished', 'room_id' => $room->id, 'winner_participant_id' => $winner->id, 'finished_at' => now()->toIso8601String()];

        $this->postCallback($event)->assertOk();
        $this->postCallback($event)->assertOk(); // reenvio do Worker: não conta duas vezes

        $this->assertDatabaseHas('game_matches', ['room_id' => $room->id, 'status' => 'finished', 'winner_participant_id' => $winner->id]);
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'finished']);
        $this->assertSame(1, $users[1]->fresh()->matches_won);
        $this->assertSame(1, $users[0]->fresh()->matches_played);
        $this->assertSame(1, $users[0]->fresh()->matches_lost);
    }

    public function test_callbacks_reject_bad_signature_old_timestamp_and_replay(): void
    {
        $this->enable();
        [$room] = $this->humanRoom();
        $event = ['type' => 'match_finished', 'room_id' => $room->id, 'winner_participant_id' => 1];
        $body = json_encode($event);

        $this->postCallback($event, $this->sign($body, 'errado'))->assertUnauthorized();
        $this->postCallback($event, $this->sign($body, 'callback', time() - 600))->assertUnauthorized();
        $this->postCallback($event, [])->assertUnauthorized();

        $same = $this->sign($body, 'callback', null, 'nonce-repetido-1');
        $this->postCallback($event, $same)->assertOk();
        $this->postCallback($event, $same)->assertOk()->assertJson(['replayed' => true]);
    }

    public function test_callbacks_are_off_when_no_secret_is_configured(): void
    {
        config(['realtime.callback_secret' => null]);
        $this->postCallback(['type' => 'match_finished', 'room_id' => 'x'])->assertNotFound();
    }

    public function test_replaced_callback_turns_the_idle_human_into_a_bot(): void
    {
        $this->enable();
        [$room, $users] = $this->humanRoom();
        $idle = $room->participants()->where('seat', 3)->first();

        $this->postCallback(['type' => 'participant_replaced', 'room_id' => $room->id, 'participant_id' => $idle->id, 'bot_key' => 'commitzilla'])->assertOk();

        $idle->refresh();
        $this->assertSame('bot', $idle->kind);
        $this->assertSame('commitzilla', $idle->bot_key);
        $this->assertNull($idle->user_id);
    }

    public function test_the_cloudflare_engine_stays_off_without_the_parity_release(): void
    {
        $this->enable();
        config(['realtime.engine_parity_ack' => null]);
        $this->assertFalse(CloudflareMatchGateway::enabled());

        config(['realtime.engine_parity_ack' => 'versao-antiga']);
        $this->assertFalse(CloudflareMatchGateway::enabled());

        config(['realtime.engine_parity_ack' => CloudflareMatchGateway::PARITY_VERSION]);
        $this->assertTrue(CloudflareMatchGateway::enabled());
    }

    public function test_the_release_version_matches_the_parity_fixture(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/parity/scenarios.json')), true);

        $this->assertSame($fixture['parity_version'], CloudflareMatchGateway::PARITY_VERSION);
    }

    public function test_bot_steps_of_a_cloudflare_match_are_forwarded_to_the_durable_object(): void
    {
        $this->enable();
        [$room, $users] = $this->humanRoom();
        GameMatch::create(['room_id' => $room->id, 'status' => 'active', 'current_seat' => 1, 'turn_number' => 1, 'version' => 1, 'state' => ['engine' => 'cloudflare']]);
        Http::fake(['worker.example/*' => Http::response(['played' => true, 'version' => 6])]);

        $this->actingAs($users[0])->post(route('games.bots', $room), ['version' => 5])->assertRedirect();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), "/match/{$room->id}/bots") && $r->data()['version'] === 5);
        $this->actingAs(User::factory()->create())->post(route('games.bots', $room), ['version' => 5])->assertNotFound();
    }

    public function test_a_worker_failure_on_a_bot_step_never_breaks_the_table(): void
    {
        $this->enable();
        [$room, $users] = $this->humanRoom();
        GameMatch::create(['room_id' => $room->id, 'status' => 'active', 'current_seat' => 1, 'turn_number' => 1, 'version' => 1, 'state' => ['engine' => 'cloudflare']]);
        Http::fake(['worker.example/*' => Http::response('boom', 500)]);

        $this->actingAs($users[0])->post(route('games.bots', $room), ['version' => 1])->assertRedirect();
    }
}
