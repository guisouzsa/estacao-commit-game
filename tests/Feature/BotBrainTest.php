<?php

namespace Tests\Feature;

use App\Services\BotBrain;
use App\Services\GameEngine;
use Tests\TestCase;

/**
 * Estratégia dos bots (espelho de worker/src/bot.ts, paridade em tests/parity). O bot só usa a própria
 * mão e o que está à vista de todos na mesa: etapa/percurso de cada um e quem está bloqueado.
 */
class BotBrainTest extends TestCase
{
    private function player(int $seat, array $hand, int $step, bool $blocked = false, int $pending = 0): array
    {
        return [
            'seat' => $seat, 'hand' => $hand, 'path' => array_slice(GameEngine::REQUIRED, 0, $step),
            'step' => $step, 'blocked' => $blocked, 'pending_discard' => $pending,
        ];
    }

    /** Mesa com o bot no assento 2 (id "2") e três adversários na etapa informada. */
    private function table(array $botHand, int $botStep, array $others = [4, 4, 4], array $deck = ['git_add', 'git_commit']): array
    {
        $filler = ['git_add', 'git_commit', 'git_push', 'git_pull'];

        return [
            'deck' => $deck,
            'discard' => [],
            'players' => [
                '1' => $this->player(1, $filler, $others[0]),
                '2' => $this->player(2, $botHand, $botStep),
                '3' => $this->player(3, $filler, $others[1]),
                '4' => $this->player(4, $filler, $others[2]),
            ],
        ];
    }

    public function test_it_plays_the_required_card_and_the_code_block(): void
    {
        $brain = new BotBrain;

        $this->assertSame(['action' => 'play_card', 'payload' => ['card' => 'git_add']], $brain->decide($this->table(['git_push', 'git_add'], 4), '2'));
        $this->assertSame(['action' => 'play_block', 'payload' => []], $brain->decide($this->table(['git_push'], 3), '2'));
    }

    public function test_it_exchanges_the_card_its_path_no_longer_needs_instead_of_the_oldest(): void
    {
        // Precisa de git_add (etapa 4). git_commit ainda será usado; git_clone já ficou para trás.
        $decision = (new BotBrain)->decide($this->table(['git_commit', 'git_clone', 'git_push'], 4), '2');

        $this->assertSame(['action' => 'exchange', 'payload' => ['card' => 'git_clone']], $decision);
    }

    public function test_extra_copies_beyond_what_is_left_are_the_first_to_go(): void
    {
        // Etapa 14 (falta 1 commit): a segunda cópia de commit sobra; push e PR ainda serão usados.
        $decision = (new BotBrain)->decide($this->table(['git_push', 'git_commit', 'git_commit', 'pull_request'], 14), '2');
        $this->assertSame('play_card', $decision['action']); // tem o commit da vez

        $decision = (new BotBrain)->decide($this->table(['git_push', 'pull_request', 'git_push', 'git_add'], 15), '2');
        $this->assertSame(['action' => 'exchange', 'payload' => ['card' => 'git_push']], $decision); // 2ª cópia de push
    }

    public function test_when_over_the_hand_limit_it_discards_the_least_useful_card(): void
    {
        $state = $this->table(['git_add', 'git_commit', 'git_clone', 'git_commit', 'git_push', 'git_add', 'git_pull'], 5);
        $state['players']['2']['pending_discard'] = 1;

        $this->assertSame(['action' => 'discard_excess', 'payload' => ['card' => 'git_clone']], (new BotBrain)->decide($state, '2'));
    }

    public function test_merge_conflict_only_against_a_leader_close_to_winning(): void
    {
        $brain = new BotBrain;
        $hand = ['merge_conflict', 'git_push', 'git_clone'];

        // Líder (assento 4) na etapa 14: ataca.
        $this->assertSame(
            ['action' => 'play_card', 'payload' => ['card' => 'merge_conflict', 'target_id' => '4']],
            $brain->decide($this->table($hand, 4, [4, 4, 14]), '2'),
        );
        // Ninguém perto do fim: guarda a carta e troca outra.
        $this->assertSame('exchange', $brain->decide($this->table($hand, 4, [6, 8, 10]), '2')['action']);
        // Líder já bloqueado: não ataca de novo.
        $state = $this->table($hand, 4, [4, 4, 14]);
        $state['players']['4']['blocked'] = true;
        $this->assertSame('exchange', $brain->decide($state, '2')['action']);
    }

    public function test_ties_target_whoever_plays_next_not_a_fixed_seat(): void
    {
        // Assentos 1 e 3 empatados na etapa 13; depois do 2 joga o 3.
        $decision = (new BotBrain)->decide($this->table(['merge_conflict', 'git_push'], 4, [13, 13, 4]), '2');

        $this->assertSame('3', $decision['payload']['target_id']);
    }

    public function test_clear_credentials_follows_the_training_rule(): void
    {
        $brain = new BotBrain;
        $hand = ['clear_credentials', 'git_push', 'git_add'];

        // Os dois no Treinamento e o alvo mais adiantado: usa.
        $this->assertSame(
            ['action' => 'play_card', 'payload' => ['card' => 'clear_credentials', 'target_id' => '1']],
            $brain->decide($this->table($hand, 1, [2, 1, 0]), '2'),
        );
        // Alvo já fora do Treinamento: a regra não permite, não tenta.
        $this->assertSame('exchange', $brain->decide($this->table($hand, 1, [5, 1, 0]), '2')['action']);
    }

    public function test_it_never_uses_hidden_information(): void
    {
        $brain = new BotBrain;
        $state = $this->table(['git_commit', 'git_clone', 'git_push'], 4, [6, 2, 9], ['git_add', 'git_add', 'git_add']);
        $expected = $brain->decide($state, '2');

        // Mudar o que o bot NÃO pode ver (mãos alheias, ordem e conteúdo do baralho, descarte) não muda nada.
        $other = $state;
        $other['deck'] = ['pull_request', 'merge_conflict'];
        $other['discard'] = ['git_add', 'git_add'];
        $other['players']['1']['hand'] = ['merge_conflict', 'merge_conflict'];
        $other['players']['3']['hand'] = ['pull_request'];
        $other['players']['4']['hand'] = [];

        $this->assertSame($expected, $brain->decide($other, '2'));
        $this->assertSame($expected, $brain->decide($state, '2')); // determinístico
    }
}
