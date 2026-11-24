<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PARIDADE entre o motor PHP e o do Worker. O PHP é a referência: este teste executa os cenários de
 * tests/parity/scenarios.json no GameEngine e compara cada passo com o "expected" do arquivo; o teste
 * worker/test/parity.test.ts executa os MESMOS cenários no motor TypeScript e compara com o MESMO arquivo.
 * Se os dois passam, os dois motores produzem resultados idênticos nesses cenários.
 *
 * Regerar o "expected" (só quando a regra do PHP mudar de propósito):
 *   PARITY_UPDATE=1 php artisan test --filter=EngineParityTest
 */
class EngineParityTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'tests/parity/scenarios.json';

    public function test_every_scenario_matches_the_shared_fixture(): void
    {
        $path = base_path(self::FILE);
        $doc = json_decode(file_get_contents($path), true);

        foreach ($doc['scenarios'] as $i => $scenario) {
            $actual = $this->runScenario($scenario);

            if (getenv('PARITY_UPDATE')) {
                $doc['scenarios'][$i]['expected'] = $actual;

                continue;
            }
            $this->assertArrayHasKey('expected', $scenario, "Cenário sem expected: {$scenario['name']}");
            $this->assertEquals($scenario['expected'], $actual, "Cenário divergente: {$scenario['name']}");
        }

        if (getenv('PARITY_UPDATE')) {
            file_put_contents($path, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            $this->markTestSkipped('Fixture regenerada a partir do motor PHP.');
        }
    }

    public function test_a_new_match_gives_everyone_the_git_login_and_the_right_deck(): void
    {
        $users = User::factory()->count(4)->create()->values();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'waiting']);
        foreach ($users as $i => $user) {
            $room->participants()->create(['user_id' => $user->id, 'kind' => 'user', 'is_host' => $i === 0, 'seat' => $i + 1, 'joined_at' => now()]);
        }

        $match = app(GameEngine::class)->create($room);
        $state = $match->state;

        $this->assertSame(62, count($state['deck']));
        $this->assertNotContains('git_login', $state['deck']);
        foreach ($state['players'] as $player) {
            $this->assertCount(4, $player['hand']);
            $this->assertSame(1, count(array_keys($player['hand'], 'git_login', true)));
        }
        $this->assertSame(1, $match->version);
        $this->assertSame(1, $match->turn_number);
        // Mesmo conteúdo que o Worker precisa ter (constante compartilhada com worker/test/parity.test.ts).
        $all = [...$state['deck'], ...array_merge(...array_column($state['players'], 'hand'))];
        $this->assertSame(['git_login' => 4, 'git_clone' => 7, 'git_checkout' => 7, 'git_pull' => 4, 'git_add' => 16, 'git_commit' => 16, 'git_push' => 16, 'pull_request' => 4, 'clear_credentials' => 2, 'merge_conflict' => 2], $this->counts($all));
    }

    public function test_bots_only_matches_always_end_with_a_valid_winner(): void
    {
        $engine = app(GameEngine::class);
        $room = GameRoom::create(['host_user_id' => User::factory()->create()->id, 'status' => 'waiting']);
        $first = $room->participants()->create(['user_id' => $room->host_user_id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        foreach ([2 => 0, 3 => 1, 4 => 2] as $seat => $n) {
            $room->participants()->create(['kind' => 'bot', 'bot_key' => config("bots.catalog.$n.key"), 'is_host' => false, 'seat' => $seat, 'joined_at' => now()]);
        }
        $match = $engine->create($room);
        // O "humano" do assento 1 também joga como bot nesta simulação: troca o tipo só para o teste.
        $first->forceFill(['kind' => 'bot', 'bot_key' => config('bots.catalog.3.key'), 'user_id' => null, 'is_host' => false])->save();

        for ($i = 0; $i < 4000 && $match->status === 'active'; $i++) {
            $engine->botStep($match, $room);
        }

        $this->assertSame('finished', $match->status);
        $winner = $match->state['players'][(string) $match->winner_participant_id];
        $this->assertCount(18, $winner['path']);
        $this->assertSame('pull_request', end($winner['path']));
        $total = count($match->state['deck']) + count($match->state['discard']);
        foreach ($match->state['players'] as $p) {
            $total += count($p['hand']) + count(array_filter($p['path'], fn ($c) => ! str_starts_with($c, 'block_')));
        }
        $this->assertSame(78, $total, 'nenhuma carta criada ou perdida');
    }

    /** @return array<string, mixed> */
    private function runScenario(array $scenario): array
    {
        $users = User::factory()->count(4)->create()->values();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'active']);
        $idBySeat = [];
        foreach ($scenario['participants'] as $n => $p) {
            $attributes = $p['kind'] === 'bot'
                ? ['kind' => 'bot', 'bot_key' => config("bots.catalog.$n.key")]
                : ['kind' => 'user', 'user_id' => $users[$n]->id];
            $idBySeat[$p['seat']] = $room->participants()->create([...$attributes, 'is_host' => $n === 0, 'seat' => $p['seat'], 'joined_at' => now()])->id;
        }
        $seatById = array_flip($idBySeat);

        $state = $scenario['state'];
        $players = [];
        foreach ($state['players'] as $seat => $player) {
            $players[(string) $idBySeat[(int) $seat]] = ['seat' => (int) $seat, ...$player];
        }
        $match = GameMatch::create([
            'room_id' => $room->id, 'status' => 'active', 'current_seat' => $state['current_seat'],
            'turn_number' => $state['turn_number'], 'version' => $state['version'],
            'state' => ['deck' => $state['deck'], 'discard' => $state['discard'], 'players' => $players, 'history' => $state['history']],
        ]);

        $engine = app(GameEngine::class);
        $results = [];
        foreach ($scenario['steps'] as $step) {
            if ($step['do'] === 'bot') {
                $result = ['played' => $engine->botStep($match, $room)];
            } else {
                $participant = $room->participants()->where('seat', $step['seat'])->firstOrFail();
                $payload = $step['payload'];
                if (isset($payload['target_id'])) {
                    $payload['target_id'] = $idBySeat[$payload['target_id']];
                }
                try {
                    $engine->act($match, $participant, $step['action'], $payload, (string) Str::uuid());
                    $result = ['ok' => true];
                } catch (ValidationException $e) {
                    $result = ['error' => ['field' => array_key_first($e->errors()), 'message' => $e->errors()[array_key_first($e->errors())][0]]];
                }
            }
            $match->refresh();
            $results[] = ['result' => $result, 'snapshot' => $this->snapshot($match, $seatById, $scenario['unordered'] ?? false)];
        }

        return $results;
    }

    /** @param  array<int, int>  $seatById */
    private function snapshot(GameMatch $match, array $seatById, bool $unordered): array
    {
        $state = $match->state;
        $players = [];
        foreach ($state['players'] as $id => $p) {
            $players[(string) $p['seat']] = [
                'hand' => $p['hand'], 'path' => $p['path'], 'step' => $p['step'],
                'blocked' => (bool) $p['blocked'], 'pending_discard' => $p['pending_discard'] ?? 0,
                'recent_swaps' => $p['recent_swaps'] ?? [],
            ];
        }
        ksort($players);

        $base = [
            'status' => $match->status, 'current_seat' => (int) $match->current_seat, 'turn_number' => (int) $match->turn_number,
            'version' => (int) $match->version,
            'winner_seat' => $match->winner_participant_id ? $seatById[$match->winner_participant_id] : null,
            'finished' => $match->finished_at !== null,
        ];

        if ($unordered) {
            // Sorteio no reembaralhamento: compara o que não depende dele.
            $all = [...$state['deck'], ...$state['discard'], ...array_merge(...array_column($players, 'hand'))];
            sort($all);

            return [...$base, 'all_cards' => $all, 'deck_size' => count($state['deck']), 'discard_size' => count($state['discard']),
                'hand_sizes' => array_map(fn ($p) => count($p['hand']), $players), 'steps' => array_map(fn ($p) => $p['step'], $players)];
        }

        $history = array_map(function ($h) use ($seatById) {
            $item = ['seat' => isset($h['participant_id']) ? $seatById[$h['participant_id']] : null, 'message' => $h['message'], 'turn' => $h['turn']];
            if (! empty($h['notify'])) {
                $item['notify'] = true;
            }

            return $item;
        }, $state['history']);

        return [...$base, 'deck' => $state['deck'], 'discard' => $state['discard'], 'players' => $players, 'history' => $history];
    }

    /** @param  list<string>  $cards */
    private function counts(array $cards): array
    {
        $c = array_count_values($cards);
        arsort($c);
        $ordered = [];
        foreach (['git_login', 'git_clone', 'git_checkout', 'git_pull', 'git_add', 'git_commit', 'git_push', 'pull_request', 'clear_credentials', 'merge_conflict'] as $k) {
            $ordered[$k] = $c[$k] ?? 0;
        }

        return $ordered;
    }
}
