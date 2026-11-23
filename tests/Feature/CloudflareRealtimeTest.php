<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\User;
use App\Services\CloudflareRealtime;
use App\Services\RoomStateBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Ponte Laravel -> Worker (REALTIME_DRIVER=cloudflare). Segredos fictícios. */
class CloudflareRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'realtime.driver' => 'cloudflare',
            'realtime.worker_url' => 'https://worker.example',
            'realtime.token_secret' => 'tok-secret',
            'realtime.notify_secret' => 'notify-secret',
        ]);
    }

    private function roomFor(User $host): GameRoom
    {
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting', 'bot_slots' => 0]);
        $room->participants()->create(['user_id' => $host->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);

        return $room;
    }

    public function test_disabled_by_default_token_endpoint_is_404(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/realtime/token', ['channel' => 'rooms.'.fake()->uuid()])->assertNotFound();
    }

    public function test_participant_gets_a_signed_short_lived_token_for_their_room(): void
    {
        $this->enable();
        $user = User::factory()->create();
        $room = $this->roomFor($user);

        $response = $this->actingAs($user)->postJson('/realtime/token', ['channel' => 'rooms.'.$room->id])->assertOk();

        $this->assertSame('wss://worker.example/ws/rooms.'.$room->id, $response->json('url'));
        [$body, $sig] = explode('.', $response->json('token'));
        $this->assertSame(rtrim(strtr(base64_encode(hash_hmac('sha256', $body, 'tok-secret', true)), '+/', '-_'), '='), $sig);
        $claims = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        $this->assertSame('rooms.'.$room->id, $claims['ch']);
        $this->assertSame('user:'.$user->id, $claims['sub']);
        $this->assertLessThanOrEqual(time() + 60, $claims['exp']);
    }

    public function test_outsiders_and_anonymous_cannot_get_a_room_token(): void
    {
        $this->enable();
        $room = $this->roomFor(User::factory()->create());
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->postJson('/realtime/token', ['channel' => 'rooms.'.$room->id])->assertForbidden();
        $this->actingAs($stranger)->postJson('/realtime/token', ['channel' => 'App.Models.User.'.$room->host_user_id])->assertForbidden();
        $this->actingAs($stranger)->postJson('/realtime/token', ['channel' => 'App.Models.User.'.$stranger->id])->assertOk();
        auth()->logout();
        $this->postJson('/realtime/token', ['channel' => 'rooms.'.$room->id])->assertUnauthorized();
    }

    public function test_broadcaster_posts_a_signed_notice_to_the_worker_instead_of_reverb(): void
    {
        $this->enable();
        Http::fake(['worker.example/*' => Http::response(['delivered' => 1])]);

        DB::transaction(fn () => app(RoomStateBroadcaster::class)->publish('0190aaaa-0000-7000-8000-000000000001', 'game_updated'));

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();
            $expected = hash_hmac('sha256', $request->header('X-Timestamp')[0].'.'.$request->header('X-Nonce')[0].'.'.hash('sha256', $body), 'notify-secret');

            return $request->url() === 'https://worker.example/notify/rooms.0190aaaa-0000-7000-8000-000000000001'
                && $request->header('X-Signature')[0] === $expected
                && json_decode($body, true) === ['event' => 'room.state.changed', 'data' => ['roomId' => '0190aaaa-0000-7000-8000-000000000001', 'change' => 'game_updated']];
        });
    }

    public function test_worker_outage_never_breaks_the_committed_action(): void
    {
        $this->enable();
        Http::fake(['worker.example/*' => Http::response('boom', 500)]);

        DB::transaction(fn () => app(RoomStateBroadcaster::class)->publish('0190aaaa-0000-7000-8000-000000000001', 'game_updated'));

        $this->assertTrue(CloudflareRealtime::enabled()); // chegou aqui sem exceção
    }
}
