<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Troca de cartas: mais variedade (sem repetir a carta trocada nem formar trincas), sem tirar a sorte do jogo. */
class CardExchangeDistributionTest extends TestCase
{
    use RefreshDatabase;

    private GameRoom $room;
    private $users;
    private int $me;

    private function start(): void
    {
        // A simulação faz mais de 60 jogadas por minuto: sem o limitador de taxa (que não é o que se testa aqui).
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->users = User::factory()->count(4)->create()->values();
        $this->room = GameRoom::create(['host_user_id' => $this->users[0]->id, 'status' => 'waiting']);
        foreach ($this->users as $i => $user) {
            $this->room->participants()->create(['user_id' => $user->id, 'kind' => 'user', 'is_host' => $i === 0, 'seat' => $i + 1, 'joined_at' => now()]);
        }
        $this->actingAs($this->users[0])->post(route('games.start', $this->room));
        $this->me = $this->room->participants()->where('seat', 1)->firstOrFail()->id;
    }

    private function setUpState(array $hand, array $deck, array $recent = []): GameMatch
    {
        $match = GameMatch::firstOrFail();
        $state = $match->state;
        $state['players'][(string) $this->me]['hand'] = $hand;
        $state['players'][(string) $this->me]['recent_swaps'] = $recent;
        $state['players'][(string) $this->me]['pending_discard'] = 0;
        $state['deck'] = $deck;
        $state['discard'] = [];
        $match->forceFill(['state' => $state, 'current_seat' => 1])->save();

        return $match->fresh();
    }

    /** @return array{0: array, 1: GameMatch} mão depois da troca e a partida */
    private function doSwap(GameMatch $match, string $card): array
    {
        $this->actingAs($this->users[0])->post(route('games.action', $this->room), [
            'action_uuid' => (string) Str::uuid(), 'action' => 'exchange', 'card' => $card, 'version' => $match->version,
        ])->assertSessionHasNoErrors()->assertRedirect(); // 409/429 não passam despercebidos
        $after = $match->fresh();

        return [$after->state['players'][(string) $this->me]['hand'], $after];
    }

    public function test_the_card_just_traded_is_skipped_when_an_alternative_is_near_the_top(): void
    {
        $this->start();
        $match = $this->setUpState(['git_add', 'git_commit', 'git_push', 'git_clone'], ['git_add', 'git_pull', 'git_commit', 'git_login']);

        [$hand] = $this->doSwap($match, 'git_add');

        $this->assertSame(['git_commit', 'git_push', 'git_clone', 'git_pull'], $hand); // não devolveu o git_add
    }

    public function test_a_third_equal_card_is_avoided(): void
    {
        $this->start();
        $match = $this->setUpState(['git_add', 'git_add', 'git_commit', 'git_clone'], ['git_add', 'git_push', 'git_pull']);

        [$hand] = $this->doSwap($match, 'git_clone');

        $this->assertSame(2, count(array_keys($hand, 'git_add', true))); // continua com 2, não 3
        $this->assertContains('git_push', $hand);
    }

    public function test_recent_swaps_are_not_handed_back_in_a_cycle(): void
    {
        $this->start();
        // O jogador já trocou git_add e git_push há pouco; o topo do baralho tem exatamente essas.
        $match = $this->setUpState(['git_commit', 'git_clone', 'git_pull', 'git_checkout'], ['git_add', 'git_push', 'git_commit', 'merge_conflict'], ['git_add', 'git_push']);

        [$hand, $after] = $this->doSwap($match, 'git_pull');

        $this->assertContains('git_commit', $hand);          // pulou git_add e git_push (trocas recentes)
        $this->assertNotContains('git_add', $hand);
        $state = $after->state['players'][(string) $this->me];
        $this->assertSame(['git_add', 'git_push', 'git_pull'], $state['recent_swaps']); // histórico curto (máx. 3)
    }

    public function test_history_keeps_only_the_last_three_swaps(): void
    {
        $this->start();
        $match = $this->setUpState(['git_commit', 'git_clone', 'git_pull', 'git_checkout'], ['merge_conflict', 'git_add', 'git_push', 'clear_credentials'], ['a', 'b', 'c']);

        [, $after] = $this->doSwap($match, 'git_clone');

        $this->assertSame(['b', 'c', 'git_clone'], $after->state['players'][(string) $this->me]['recent_swaps']);
    }

    public function test_when_nothing_better_is_near_the_top_the_top_card_is_used_and_nothing_is_forced(): void
    {
        $this->start();
        // Baralho só com a carta trocada: o jogo continua (compra do topo, como sempre).
        $match = $this->setUpState(['git_add', 'git_commit', 'git_push', 'git_clone'], ['git_add', 'git_add', 'git_add']);

        [$hand, $after] = $this->doSwap($match, 'git_add');

        $this->assertCount(4, $hand);
        $this->assertSame(2, count($after->state['deck']));
    }

    public function test_quantities_and_rules_are_untouched(): void
    {
        $this->start();
        $match = $this->setUpState(['git_add', 'git_commit', 'git_push', 'git_clone'], ['git_pull', 'git_add', 'git_login', 'git_commit', 'git_push']);
        $before = count($match->state['deck']) + count($match->state['players'][(string) $this->me]['hand']) + count($match->state['discard']);

        [$hand, $after] = $this->doSwap($match, 'git_push');

        $afterTotal = count($after->state['deck']) + count($hand) + count($after->state['discard']);
        $this->assertSame($before, $afterTotal);                       // nenhuma carta criada ou perdida
        $this->assertCount(4, $hand);                                   // mão do mesmo tamanho
        $this->assertSame(['git_push'], $after->state['discard']);      // a trocada vai ao descarte
        $this->assertSame(2, $after->current_seat);                     // a vez passa (regra de sempre)
        $this->assertSame($match->version + 1, $after->version);
    }

    public function test_simulation_repeats_far_less_than_a_plain_draw_and_stays_random(): void
    {
        $this->start();
        mt_srand(20261124);
        $types = ['git_add', 'git_commit', 'git_push', 'git_clone', 'git_pull', 'git_checkout', 'merge_conflict', 'clear_credentials', 'pull_request'];
        $weights = ['git_add' => 16, 'git_commit' => 16, 'git_push' => 16, 'git_clone' => 7, 'git_checkout' => 7, 'git_pull' => 4, 'pull_request' => 4, 'merge_conflict' => 2, 'clear_credentials' => 2];
        $pool = [];
        foreach ($weights as $card => $n) {
            $pool = [...$pool, ...array_fill(0, $n, $card)];
        }

        $trials = 120;
        $smartSame = $plainSame = $triples = 0;
        $received = [];
        for ($t = 0; $t < $trials; $t++) {
            $deck = $pool;
            shuffle($deck);
            $hand = ['git_add', 'git_commit', 'git_add', 'git_clone'];
            $traded = 'git_add';
            $match = $this->setUpState($hand, $deck);

            $plainSame += $deck[0] === $traded ? 1 : 0; // o que acontecia antes: compra o topo
            [$newHand] = $this->doSwap($match, $traded);
            $drawn = array_values(array_diff_assoc($newHand, ['git_commit', 'git_add', 'git_clone']))[0] ?? $newHand[3];
            $smartSame += $drawn === $traded ? 1 : 0;
            $received[$drawn] = true;
            $triples += max(array_count_values($newHand)) >= 3 ? 1 : 0;
        }

        // Antes: ~ 1 em cada 5-6 trocas devolvia a mesma carta (git_add é 16 de 78). Agora, raríssimo.
        $this->assertGreaterThan(10, $plainSame, 'o cenário base precisa mostrar o problema');
        $this->assertLessThanOrEqual(2, $smartSame, "repetiu {$smartSame} de {$trials} (antes {$plainSame})");
        $this->assertSame(0, $triples, 'nenhuma trinca nova nas trocas');
        $this->assertGreaterThanOrEqual(5, count($received), 'continua variado: não entrega sempre as mesmas cartas');
        fwrite(STDERR, "\n[troca] repetição da mesma carta: antes {$plainSame}/{$trials}, agora {$smartSame}/{$trials}; tipos recebidos: ".count($received)."\n");
    }
}
