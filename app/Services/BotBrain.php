<?php

namespace App\Services;

/**
 * Decisão dos bots. Espelho exato de worker/src/bot.ts (paridade testada por tests/parity).
 *
 * O bot decide SÓ com o que um jogador da mesa poderia saber: a própria mão e o estado público
 * (etapa e percurso de cada um, quem está bloqueado). Nunca olha a mão dos outros nem o baralho.
 * Determinístico: mesma situação, mesma jogada (sem sorteio aqui).
 */
class BotBrain
{
    /** Primeiro passo da Integração (antes dele é o Treinamento: login, clone, checkout). */
    private const TRAINING_END = 3;

    /**
     * @param  array<string, mixed>  $state  estado da partida (players indexados pelo id do participante)
     * @return array{action: string, payload: array<string, string>}
     */
    public function decide(array $state, string $me): array
    {
        $player = $state['players'][$me];

        if (($player['pending_discard'] ?? 0) > 0) {
            return ['action' => 'discard_excess', 'payload' => ['card' => $player['hand'][$this->leastValuable($player)]]];
        }

        $required = GameEngine::REQUIRED[$player['step']] ?? null;
        if ($required !== null && str_starts_with($required, 'block_')) {
            return ['action' => 'play_block', 'payload' => []];
        }
        if ($required !== null && in_array($required, $player['hand'], true)) {
            return ['action' => 'play_card', 'payload' => ['card' => $required]];
        }

        // Ataques só contra quem está realmente na frente (informação pública) e quando a regra permite.
        $top = $this->leader($state, $me);
        if ($top !== null) {
            [$targetId, $target] = $top;
            if (in_array('merge_conflict', $player['hand'], true) && ! ($target['blocked'] ?? false)
                && $target['step'] >= 12 && $target['step'] > $player['step']) {
                return ['action' => 'play_card', 'payload' => ['card' => 'merge_conflict', 'target_id' => $targetId]];
            }
            if (in_array('clear_credentials', $player['hand'], true) && $player['step'] < self::TRAINING_END
                && $target['step'] < self::TRAINING_END && $target['step'] >= 2 && $target['step'] > $player['step']) {
                return ['action' => 'play_card', 'payload' => ['card' => 'clear_credentials', 'target_id' => $targetId]];
            }
        }

        return ['action' => 'exchange', 'payload' => ['card' => $player['hand'][$this->leastValuable($player)]]];
    }

    /**
     * Quanto uma carta da mão vale para quem a tem (maior = guardar). Cartas que o percurso não usa mais,
     * ou cópias além do que ainda falta, valem menos que qualquer carta necessária; entre as necessárias,
     * vale mais a que será usada antes.
     *
     * @param  array<string, mixed>  $player
     */
    public function cardValue(array $player, int $index): int
    {
        $card = $player['hand'][$index];
        $upcoming = array_values(array_filter(
            array_slice(GameEngine::REQUIRED, $player['step']),
            fn (string $c) => ! str_starts_with($c, 'block_'),
        ));
        $stillNeeded = count(array_keys($upcoming, $card, true));
        // Quantas cópias iguais aparecem ANTES desta na mão: a 2ª cópia só vale se o percurso usa duas.
        $copyRank = count(array_keys(array_slice($player['hand'], 0, $index), $card, true));

        if ($card === 'merge_conflict') {
            return 30; // útil a qualquer momento contra quem lidera
        }
        if ($card === 'clear_credentials') {
            return $player['step'] < self::TRAINING_END ? 25 : 0;
        }
        if ($copyRank >= $stillNeeded) {
            return $card === 'git_pull' && $player['step'] < self::TRAINING_END ? 5 : 1;
        }

        return 100 - (int) array_search($card, $upcoming, true);
    }

    /** Índice da carta menos valiosa (empate: a mais antiga da mão). @param array<string, mixed> $player */
    public function leastValuable(array $player): int
    {
        $worst = 0;
        for ($i = 1; $i < count($player['hand']); $i++) {
            if ($this->cardValue($player, $i) < $this->cardValue($player, $worst)) {
                $worst = $i;
            }
        }

        return $worst;
    }

    /**
     * Adversário mais adiantado (dado público). Empate: quem joga primeiro depois de mim; a distância
     * na ordem da mesa não favorece nenhum assento fixo.
     *
     * @param  array<string, mixed>  $state
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function leader(array $state, string $me): ?array
    {
        $mySeat = (int) $state['players'][$me]['seat'];
        $order = fn (int $seat) => ($seat - $mySeat + 4) % 4; // 1 = o próximo a jogar
        $best = null;
        foreach ($state['players'] as $id => $p) {
            $id = (string) $id;
            if ($id === $me) {
                continue;
            }
            if ($best === null || $p['step'] > $best[1]['step']
                || ($p['step'] === $best[1]['step'] && $order((int) $p['seat']) < $order((int) $best[1]['seat']))) {
                $best = [$id, $p];
            }
        }

        return $best;
    }
}
