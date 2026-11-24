<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use Tests\TestCase;

/**
 * Teste AO VIVO (pulado por padrão): Laravel real + Worker real (wrangler dev) + callback real.
 * Rode com LIVE_WORKER=1 e um SQLite de arquivo compartilhado com `php artisan serve`.
 * Não usa RefreshDatabase de propósito (o servidor precisa enxergar os dados).
 */
class CloudflareLiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('LIVE_WORKER')) {
            $this->markTestSkipped('Defina LIVE_WORKER=1 (ver worker/README.md).');
        }
        config([
            'realtime.driver' => 'cloudflare', 'realtime.game_engine' => 'cloudflare',
            'realtime.worker_url' => getenv('LIVE_WORKER_URL') ?: 'http://127.0.0.1:8787',
            'realtime.token_secret' => 'live-token', 'realtime.notify_secret' => 'live-notify', 'realtime.callback_secret' => 'live-callback',
            'game.turn_timeout_seconds' => 0,
        ]);
    }

    public function test_full_match_one_human_three_bots_through_the_real_worker_and_callback(): void
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting', 'bot_slots' => 3]);
        $room->participants()->create(['user_id' => $host->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        foreach ([2 => 'commitzilla', 3 => 'null-pointer', 4 => 'git-ninja'] as $seat => $bot) {
            $room->participants()->create(['kind' => 'bot', 'bot_key' => $bot, 'is_host' => false, 'seat' => $seat, 'joined_at' => now()]);
        }

        $this->actingAs($host)->post(route('games.start', $room))->assertRedirect(route('games.show', $room));

        $props = fn () => $this->actingAs($host)->get(route('games.show', $room))->original->getData()['page']['props'];
        $moves = 0;
        for (; $moves < 1500; $moves++) {
            $p = $props();
            if ($p['game']['status'] === 'finished') {
                break;
            }
            $me = collect($p['participants'])->firstWhere('is_viewer', true);
            $this->assertSame($me['seat'], $p['game']['current_seat'], 'mesa parou fora da vez do humano');
            [$action, $card] = ['exchange', $p['hand'][0] ?? null];
            if ($me['pending_discard'] > 0) {
                $action = 'discard_excess';
            } elseif (str_starts_with((string) $p['requiredCard'], 'block_')) {
                [$action, $card] = ['play_block', null];
            } elseif ($p['requiredCard'] && in_array($p['requiredCard'], $p['hand'], true)) {
                [$action, $card] = ['play_card', $p['requiredCard']];
            }
            $this->actingAs($host)->post(route('games.action', $room), [
                'action_uuid' => (string) \Illuminate\Support\Str::uuid(), 'action' => $action, 'card' => $card, 'version' => $p['game']['version'],
            ])->assertSessionHasNoErrors();
        }

        $final = $props();
        $this->assertSame('finished', $final['game']['status'], "não terminou em $moves jogadas");
        $this->assertNotNull($final['game']['winner_participant_id']);

        // O callback do Worker chega ao Laravel (servidor real) e fecha sala + estatísticas.
        $deadline = time() + 30;
        while (time() < $deadline && GameMatch::where('room_id', $room->id)->value('status') !== 'finished') {
            usleep(500_000);
        }
        $this->assertSame('finished', GameMatch::where('room_id', $room->id)->value('status'));
        $this->assertSame('finished', $room->fresh()->status);
        $this->assertSame(1, $host->fresh()->matches_played);
    }
}
