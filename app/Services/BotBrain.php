<?php

namespace App\Services;

/**
 * Decisão dos bots. Espelho exato de worker/src/bot.ts (paridade testada por tests/parity).
 *
 * O bot decide SÓ com o que um jogador da mesa poderia saber: a própria mão e o estado público
 * (etapa e percurso de cada um, quem está bloqueado). Nunca olha a mão dos outros nem o baralho.
 * Determinístico: mesma situação, mesma jogada (o "sorteio" da hesitação é uma conta sobre turno,
 * assento e etapa, igual nos dois motores).
 */
class BotBrain
{
    /** Primeiro passo da Integração (antes dele é o Treinamento: login, clone, checkout). */
    private const TRAINING_END = 3;

    /**
     * Desvantagem dos bots quando há pessoas na mesa (decisão de produto: o jogador real deve vencer cerca
     * de 90% das partidas contra bots). Chance, em %, de o bot "hesitar" numa vez em que poderia avançar:
     * em vez de jogar a carta ou o bloco da etapa, ele troca uma carta. Indexada pelo número de pessoas (1 a 3).
     * Calibrada com worker/sim/balance.ts; os mesmos números ficam em worker/src/bot.ts (BOT_HANDICAP).
     */
    public const HANDICAP = [1 => 67, 2 => 50, 3 => 40];

    /**
     * @param  array<string, mixed>  $state  estado da partida (players indexados pelo id do participante)
     * @param  int  $turn  turno atual da partida (GameMatch::turn_number)
     * @param  int  $humans  pessoas (contas ou visitantes) na mesa
     * @param  array<int, int>|null  $handicap  tabela de hesitação (padrão: HANDICAP)
     * @return array{action: string, payload: array<string, string>}
     */
    public function decide(array $state, string $me, int $turn = 1, int $humans = 0, ?array $handicap = null): array
    {
        $player = $state['players'][$me];

        if (($player['pending_discard'] ?? 0) > 0) {
            return ['action' => 'discard_excess', 'payload' => ['card' => $player['hand'][$this->leastValuable($player)]]];
        }

        $required = GameEngine::REQUIRED[$player['step']] ?? null;
        $canAdvance = $required !== null && (str_starts_with($required, 'block_') || in_array($required, $player['hand'], true));
        $chance = ($handicap ?? self::HANDICAP)[min($humans, 3)] ?? 0;
        if ($canAdvance && $humans > 0 && $player['hand'] !== []
            && self::handicapRoll($turn, (int) $player['seat'], (int) $player['step']) < $chance) {
            return ['action' => 'exchange', 'payload' => ['card' => $player['hand'][$this->leastValuable($player)]]];
        }
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
     * Sorteio determinístico (0 a 99) da hesitação: só depende do turno, do assento e da etapa, então o motor
     * PHP e o do Worker chegam ao mesmo número (paridade) e o navegador não consegue influenciá-lo.
     * Combina os três números e embaralha os bits (finalizador do MurmurHash3) em 32 bits sem sinal.
     */
    public static function handicapRoll(int $turn, int $seat, int $step): int
    {
        $h = ($turn * 0x9E3779B1 + $seat * 0x85EBCA77 + $step * 0xC2B2AE3D) % 4294967296;
        $h ^= $h >> 16;
        $h = self::mul32($h, 0x85EBCA6B);
        $h ^= $h >> 13;
        $h = self::mul32($h, 0xC2B2AE35);
        $h ^= $h >> 16;

        return $h % 100;
    }

    /** Multiplicação módulo 2^32 sem estourar o inteiro de 64 bits (equivale a Math.imul >>> 0). */
    private static function mul32(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;

        return ((($a & 0xFFFF) * $b) + (((($a >> 16) * $b) & 0xFFFF) << 16)) & 0xFFFFFFFF;
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
