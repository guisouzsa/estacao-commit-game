<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\RoomParticipant;
use App\Models\User;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Integridade e justiça do baralho. Os testes estatísticos usam margens largas (probabilidade de falso alarme
 * abaixo de 1 em 10 mil) para não oscilar entre execuções.
 */
class DeckFairnessTest extends TestCase
{
    use RefreshDatabase;

    private function room(array $kinds = ['user', 'user', 'user', 'user']): GameRoom
    {
        $room = GameRoom::create(['host_user_id' => User::factory()->create()->id, 'status' => 'waiting']);
        foreach ($kinds as $i => $kind) {
            $room->participants()->create([
                'user_id' => $kind === 'user' ? User::factory()->create()->id : null,
                'kind' => $kind, 'bot_key' => $kind === 'bot' ? 'bot'.Str::random(6) : null,
                'is_host' => $i === 0, 'seat' => $i + 1, 'joined_at' => now(),
            ]);
        }

        return $room;
    }

    private function totalCards(array $state): array
    {
        $all = [...$state['deck'], ...$state['discard']];
        foreach ($state['players'] as $p) {
            // os Blocos de Código são peças do tabuleiro, não cartas do baralho
            $all = [...$all, ...$p['hand'], ...array_filter($p['path'], fn ($c) => ! str_starts_with($c, 'block_'))];
        }

        return array_count_values($all);
    }

    public function test_every_new_deck_has_exactly_the_configured_composition(): void
    {
        $expected = config('game.deck');
        for ($i = 0; $i < 25; $i++) {
            $match = app(GameEngine::class)->create($this->room());
            $counts = $this->totalCards($match->state);
            ksort($counts);
            ksort($expected);
            $this->assertSame($expected, $counts, 'nenhuma carta criada, perdida ou duplicada');
        }
    }

    public function test_the_php_and_worker_decks_are_the_same_numbers(): void
    {
        $source = file_get_contents(base_path('worker/src/engine.ts'));
        preg_match('/COMMON_COUNTS[^{]*\{(.*?)\};/s', $source, $block);
        preg_match_all('/(\w+):\s*(\d+)/', $block[1] ?? '', $pairs, PREG_SET_ORDER);
        $worker = [];
        foreach ($pairs as [, $card, $qty]) {
            $worker[$card] = (int) $qty;
        }

        $this->assertSame(config('game.deck'), $worker, 'config/game.php e worker/src/engine.ts precisam ter o mesmo baralho');
    }

    public function test_the_shuffle_is_uniform_over_positions(): void
    {
        $shuffle = new ReflectionMethod(GameEngine::class, 'shuffled');
        $engine = app(GameEngine::class);
        $n = 8;
        $trials = 6000;
        $positions = array_fill(0, $n, array_fill(0, $n, 0)); // [carta][posição]
        for ($t = 0; $t < $trials; $t++) {
            foreach ($shuffle->invoke($engine, range(0, $n - 1)) as $position => $card) {
                $positions[$card][$position]++;
            }
        }

        // qui-quadrado por carta (gl = 7). Valor crítico para p = 0,0001 é ~29,9; margem folgada.
        $expected = $trials / $n;
        foreach ($positions as $card => $row) {
            $chi = array_sum(array_map(fn ($observed) => (($observed - $expected) ** 2) / $expected, $row));
            $this->assertLessThan(40, $chi, "posição da carta {$card} fora do esperado (qui² = {$chi})");
        }
    }

    public function test_the_shuffle_is_a_permutation_and_keeps_every_card(): void
    {
        $shuffle = new ReflectionMethod(GameEngine::class, 'shuffled');
        $deck = [];
        foreach (config('game.deck') as $card => $qty) {
            $deck = [...$deck, ...array_fill(0, $qty, $card)];
        }
        $out = $shuffle->invoke(app(GameEngine::class), $deck);

        $this->assertCount(count($deck), $out);
        $a = array_count_values($deck);
        $b = array_count_values($out);
        ksort($a);
        ksort($b);
        $this->assertSame($a, $b);
    }

    public function test_the_discard_pile_is_reshuffled_into_the_deck_when_it_runs_out(): void
    {
        $engine = app(GameEngine::class);
        $match = $engine->create($this->room());
        $participant = RoomParticipant::where('seat', $match->current_seat)->firstOrFail();
        $state = $match->state;
        $discarded = ['git_add', 'git_commit', 'git_push', 'git_clone', 'git_checkout'];
        $state['discard'] = $discarded;
        $state['deck'] = [];
        $card = $state['players'][(string) $participant->id]['hand'][0];
        $match->forceFill(['state' => $state, 'turn_started_at' => now()])->save();
        $before = $this->totalCards($state);

        $engine->act($match->fresh(), $participant->fresh(), 'exchange', ['card' => $card], (string) Str::uuid());

        $after = $match->fresh()->state;
        $this->assertCount(count($discarded), $after['deck'], 'a pilha de descarte virou o novo baralho e uma carta foi comprada');
        $afterCounts = $this->totalCards($after);
        ksort($before);
        ksort($afterCounts);
        $this->assertSame($before, $afterCounts, 'nenhuma carta apareceu ou sumiu na reposição');
    }

    public function test_a_full_bots_game_never_loses_or_duplicates_a_card(): void
    {
        $engine = app(GameEngine::class);
        $room = $this->room(['bot', 'bot', 'bot', 'bot']);
        $match = $engine->create($room);
        $expected = config('game.deck');
        ksort($expected);

        for ($guard = 0; $guard < 1500 && $match->status === 'active'; $guard++) {
            $this->assertTrue($engine->botStep($match, $room));
            if ($guard % 25 === 0) {
                $counts = $this->totalCards($match->state);
                ksort($counts);
                $this->assertSame($expected, $counts);
            }
        }

        $this->assertSame('finished', $match->fresh()->status);
        $counts = $this->totalCards($match->fresh()->state);
        ksort($counts);
        $this->assertSame($expected, $counts);
    }

    public function test_humans_and_bots_draw_by_exactly_the_same_rule(): void
    {
        // Mesma mão, mesmo baralho: a chance de receber `git_clone` na troca não pode depender do tipo do jogador.
        $engine = app(GameEngine::class);
        $trials = 1200;
        $hits = ['user' => 0, 'bot' => 0];
        foreach (['user', 'bot'] as $kind) {
            $room = $this->room([$kind, 'user', 'user', 'user']);
            $match = $engine->create($room);
            $p = RoomParticipant::where('room_id', $room->id)->where('seat', 1)->firstOrFail();
            for ($t = 0; $t < $trials; $t++) {
                $deck = [];
                foreach (config('game.deck') as $card => $qty) {
                    if ($card !== 'git_login') {
                        $deck = [...$deck, ...array_fill(0, $qty, $card)];
                    }
                }
                $state = $match->state;
                $state['deck'] = (new \Random\Randomizer)->shuffleArray($deck);
                $state['discard'] = [];
                $state['players'][(string) $p->id]['hand'] = ['git_login', 'git_add', 'git_commit', 'git_push'];
                $state['players'][(string) $p->id]['recent_swaps'] = [];
                GameMatch::query()->whereKey($match->id)->update(['state' => json_encode($state), 'current_seat' => 1, 'status' => 'active', 'turn_started_at' => now()]);

                $engine->act($match->fresh(), $p->fresh(), 'exchange', ['card' => 'git_push'], (string) Str::uuid());
                $hits[$kind] += in_array('git_clone', $match->fresh()->state['players'][(string) $p->id]['hand'], true) ? 1 : 0;
            }
        }

        // teste z de duas proporções; |z| < 4,5 (falso alarme ~ 7 em 1 milhão)
        $p1 = $hits['user'] / $trials;
        $p2 = $hits['bot'] / $trials;
        $pool = ($hits['user'] + $hits['bot']) / (2 * $trials);
        $z = abs($p1 - $p2) / sqrt(max(1e-9, $pool * (1 - $pool) * 2 / $trials));
        $this->assertLessThan(4.5, $z, "humano {$p1} x bot {$p2} (z = {$z})");
        // e a frequência observada bate com a esperada: ~10/67 (± o efeito do pulo de repetições)
        $this->assertEqualsWithDelta(10 / 67, $pool, 0.04);
    }

    public function test_the_client_cannot_influence_which_card_is_drawn(): void
    {
        $engine = app(GameEngine::class);
        $room = $this->room();
        $match = $engine->create($room);
        $p = RoomParticipant::where('room_id', $room->id)->where('seat', $match->current_seat)->firstOrFail();
        $state = $match->state;
        $state['deck'] = ['git_clone', 'git_checkout', 'git_pull'];
        $state['players'][(string) $p->id]['hand'] = ['git_login', 'git_add', 'git_commit', 'git_push'];
        $match->forceFill(['state' => $state, 'turn_started_at' => now()])->save();

        // campos extras (card_wanted, draw, index, seed) não existem para o servidor: nada muda o sorteio
        $engine->act($match->fresh(), $p->fresh(), 'exchange', ['card' => 'git_push', 'draw' => 'git_pull', 'index' => 2, 'seed' => 1, 'card_wanted' => 'pull_request'], (string) Str::uuid());

        $hand = $match->fresh()->state['players'][(string) $p->id]['hand'];
        $this->assertNotContains('pull_request', $hand);
        $this->assertContains(end($hand), ['git_clone', 'git_checkout', 'git_pull'], 'só pode vir do topo do baralho');
    }
}
