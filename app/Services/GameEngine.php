<?php

namespace App\Services;

use App\Models\GameAction;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\RoomParticipant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameEngine
{
    public const REQUIRED = [
        'git_login', 'git_clone', 'git_checkout',
        'block_1', 'git_add', 'git_commit',
        'block_2', 'git_add', 'git_commit',
        'block_3', 'git_add', 'git_commit',
        'block_4', 'git_add', 'git_commit',
        'git_pull', 'git_push', 'pull_request',
    ];


    public function create(GameRoom $room): GameMatch
    {
        $participants = $room->participants()->orderBy('seat')->get();
        if ($participants->count() !== GameRoom::MAX_PARTICIPANTS) {
            throw ValidationException::withMessages(['room' => 'A partida exige exatamente quatro participantes.']);
        }

        // Regra: TODOS começam com o Git Login. As 4 cópias vão uma para cada jogador e não entram
        // no baralho; o resto da mão (3 cartas comuns) sai do baralho embaralhado.
        $deck = [];
        foreach ((array) config('game.deck') as $card => $quantity) {
            $quantity -= $card === 'git_login' ? $participants->count() : 0;
            $deck = [...$deck, ...array_fill(0, max(0, $quantity), $card)];
        }
        $deck = $this->shuffled($deck);

        $players = [];
        foreach ($participants as $participant) {
            $hand = ['git_login', ...array_splice($deck, 0, 3)];
            $hand = $this->shuffled($hand);
            $players[(string) $participant->id] = [
                'seat' => $participant->seat,
                'hand' => $hand,
                'path' => [],
                'step' => 0,
                'blocked' => false,
                'pending_discard' => 0,
            ];
        }

        $firstSeat = random_int(1, GameRoom::MAX_PARTICIPANTS);
        $match = GameMatch::create([
            'room_id' => $room->id,
            'status' => 'active',
            'current_seat' => $firstSeat,
            'turn_number' => 1,
            'version' => 1,
            'turn_started_at' => now(),
            'state' => [
                'deck' => $deck,
                'discard' => [],
                'players' => $players,
                'history' => [['id' => (string) Str::uuid(), 'message' => 'A partida começou.', 'turn' => 1]],
            ],
        ]);

        \App\Support\OpsLog::event('game.created', ['room' => $room->id, 'match' => $match->id, 'engine' => 'php']);

        return $match;
    }

    public function act(GameMatch $match, RoomParticipant $participant, string $action, array $payload, string $actionUuid): void
    {
        if ($match->status !== 'active') {
            throw ValidationException::withMessages(['game' => 'Esta partida já foi finalizada.']);
        }
        if ((int) $match->current_seat !== (int) $participant->seat) {
            throw ValidationException::withMessages(['turn' => 'É a vez de outro jogador.']);
        }

        // O prazo da vez é decidido pelo servidor: humano que joga depois do tempo (com folga curta para a
        // rede) não joga mais; a vez dele é pulada pelo GameInactivityService e conta como timeout.
        $timeout = (int) config('game.turn_timeout_seconds');
        if (! $participant->isBot() && $timeout > 0 && $match->turn_started_at
            && $match->turn_started_at->copy()->addSeconds($timeout + 3)->isPast()) {
            throw ValidationException::withMessages(['turn' => 'Seu tempo acabou. A vez passou para o próximo jogador.']);
        }

        $state = $match->state;
        $key = (string) $participant->id;
        $player = &$state['players'][$key];
        if (($player['pending_discard'] ?? 0) > 0 && $action !== 'discard_excess') {
            throw ValidationException::withMessages(['hand' => 'Escolha uma carta para respeitar o limite da mão.']);
        }

        match ($action) {
            'play_card' => $this->playCard($match, $state, $participant, (string) ($payload['card'] ?? ''), isset($payload['target_id']) ? (string) $payload['target_id'] : null),
            'play_block' => $this->playBlock($match, $state, $participant),
            'exchange' => $this->exchange($match, $state, $participant, (string) ($payload['card'] ?? '')),
            'discard_excess' => $this->discardExcess($match, $state, $participant, (string) ($payload['card'] ?? '')),
            default => throw ValidationException::withMessages(['action' => 'Ação de jogo desconhecida.']),
        };

        $match->state = $state;
        $match->version++;
        $match->save();
        $this->record($match, $participant, $action, $payload, $actionUuid);

        // Jogada válida: a sequência de timeouts do humano recomeça do zero.
        if (! $participant->isBot() && (int) $participant->timeout_streak > 0) {
            RoomParticipant::query()->whereKey($participant->id)->update(['timeout_streak' => 0]);
            $participant->timeout_streak = 0;
        }
    }

    /**
     * Joga UMA vez pelo bot da vez (se a vez é de um bot). Retorna true se jogou.
     * Cada jogador, bots inclusive, joga a sua vez sozinho: nenhum bot joga junto com outro nem
     * em cadeia. Quem dá o ritmo é o cliente (ver GameMatchController::botStep), uma jogada por vez.
     */
    public function botStep(GameMatch $match, GameRoom $room): bool
    {
        if ($match->status !== 'active') {
            return false;
        }
        $bot = $room->participants()->where('seat', $match->current_seat)->where('kind', 'bot')->first();
        if (! $bot) {
            return false;
        }

        // A decisão usa só a mão do próprio bot e o estado público da mesa (ver BotBrain).
        ['action' => $action, 'payload' => $payload] = app(BotBrain::class)->decide($match->state, (string) $bot->id);

        $this->act($match, $bot, $action, $payload, (string) Str::uuid());

        return true;
    }

    /** Roda todos os bots em sequência (uso em testes e rotinas internas; a partida real usa botStep). */
    public function runBots(GameMatch $match, GameRoom $room): void
    {
        for ($guard = 0; $guard < 12 && $this->botStep($match, $room); $guard++) {
            // uma jogada por volta
        }
    }

    private function playCard(GameMatch $match, array &$state, RoomParticipant $participant, string $card, ?string $targetId): void
    {
        $key = (string) $participant->id;
        $player = &$state['players'][$key];
        $index = array_search($card, $player['hand'], true);
        if ($index === false) {
            throw ValidationException::withMessages(['card' => 'Essa carta não está na sua mão.']);
        }

        $required = self::REQUIRED[$player['step']] ?? null;
        if ($card === 'git_pull' && $player['step'] < 3) {
            $this->removeCard($player['hand'], $index);
            $state['discard'][] = $card;
            $this->draw($state, $player, 2);
            $this->history($state, $participant, 'usou git pull no Treinamento e comprou duas cartas.', $match);
            $this->finishOrAdvance($match, $state, $participant);

            return;
        }

        if (in_array($card, ['clear_credentials', 'merge_conflict'], true)) {
            $this->attack($match, $state, $participant, $card, $targetId);
            $this->removeCard($player['hand'], $index);
            $state['discard'][] = $card;
            $this->draw($state, $player, 1);
            $this->finishOrAdvance($match, $state, $participant);

            return;
        }

        if ($card !== $required || str_starts_with((string) $required, 'block_')) {
            throw ValidationException::withMessages(['card' => 'Essa carta não é a próxima etapa do seu percurso.']);
        }

        $this->removeCard($player['hand'], $index);
        $player['path'][] = $card;
        $player['step']++;
        $this->history($state, $participant, 'jogou '.$this->label($card).'.', $match);

        if ($card === 'pull_request') {
            $match->status = 'finished';
            $match->winner_participant_id = $participant->id;
            $match->finished_at = now();
            $this->history($state, $participant, 'concluiu uma Pull Request válida e venceu!', $match);

            return;
        }

        $this->draw($state, $player, 1);
        $this->finishOrAdvance($match, $state, $participant);
    }

    private function playBlock(GameMatch $match, array &$state, RoomParticipant $participant): void
    {
        $player = &$state['players'][(string) $participant->id];
        $required = self::REQUIRED[$player['step']] ?? null;
        if (! str_starts_with((string) $required, 'block_')) {
            throw ValidationException::withMessages(['card' => 'O próximo passo não é um Bloco de Código.']);
        }
        $player['path'][] = $required;
        $player['step']++;
        $this->history($state, $participant, 'encaixou o '.str_replace('_', ' ', $required).'.', $match);
        if ($required === 'block_4') {
            $this->draw($state, $player, 1);
        }
        $this->finishOrAdvance($match, $state, $participant);
    }

    private function exchange(GameMatch $match, array &$state, RoomParticipant $participant, string $card): void
    {
        $player = &$state['players'][(string) $participant->id];
        $index = array_search($card, $player['hand'], true);
        if ($index === false) {
            throw ValidationException::withMessages(['card' => 'Escolha uma carta da sua mão para trocar.']);
        }
        $this->removeCard($player['hand'], $index);
        $state['discard'][] = $card;
        // Troca com variedade: a carta nova evita a que acabou de sair e as últimas trocas do
        // jogador, e não forma trinca na mão. Continua sendo sorte (ver pickIndex): nada é entregue
        // "de propósito" e a mão pode, sim, continuar sem a carta que o jogador queria.
        $recent = $player['recent_swaps'] ?? [];
        $this->draw($state, $player, 1, [$card, ...$recent]);
        $player['recent_swaps'] = array_slice([...$recent, $card], -3);
        $this->history($state, $participant, 'trocou uma carta.', $match);
        $this->advance($match, $state);
    }

    private function discardExcess(GameMatch $match, array &$state, RoomParticipant $participant, string $card): void
    {
        $player = &$state['players'][(string) $participant->id];
        $index = array_search($card, $player['hand'], true);
        if ($index === false) {
            throw ValidationException::withMessages(['card' => 'Escolha uma carta da sua mão para descartar.']);
        }
        $this->removeCard($player['hand'], $index);
        $state['discard'][] = $card;
        $player['pending_discard'] = max(0, count($player['hand']) - 6);
        if ($player['pending_discard'] === 0) {
            $this->advance($match, $state);
        }
    }

    private function attack(GameMatch $match, array &$state, RoomParticipant $participant, string $card, ?string $targetId): void
    {
        if (! $targetId || ! isset($state['players'][$targetId]) || $targetId === (string) $participant->id) {
            throw ValidationException::withMessages(['target' => 'Escolha outro jogador como alvo.']);
        }
        $attacker = &$state['players'][(string) $participant->id];
        $target = &$state['players'][$targetId];
        if ($card === 'clear_credentials') {
            if ($attacker['step'] >= 3 || $target['step'] >= 3) {
                throw ValidationException::withMessages(['target' => 'Limpar Credenciais só funciona entre jogadores no Treinamento.']);
            }
            foreach ($target['path'] as $played) {
                $state['discard'][] = $played;
            }
            $target['path'] = [];
            $target['step'] = 0;
        } else {
            if ($target['blocked']) {
                throw ValidationException::withMessages(['target' => 'Esse jogador já está resolvendo um Merge Conflict.']);
            }
            $target['blocked'] = true;
        }
        $this->history($state, $participant, 'usou '.$this->label($card).'.', $match);
    }

    private function finishOrAdvance(GameMatch $match, array &$state, RoomParticipant $participant): void
    {
        if ($match->status === 'finished') {
            return;
        }
        $player = &$state['players'][(string) $participant->id];
        $player['pending_discard'] = max(0, count($player['hand']) - 6);
        if ($player['pending_discard'] === 0) {
            $this->advance($match, $state);
        }
    }

    /** Passa a vez ao próximo jogador sem jogada (usado no timeout). Persistir fica com quem chama. */
    public function skipTurn(GameMatch $match): void
    {
        $state = $match->state;
        $this->advance($match, $state);
        $match->state = $state;
    }

    private function advance(GameMatch $match, array &$state): void
    {
        for ($i = 0; $i < GameRoom::MAX_PARTICIPANTS; $i++) {
            $match->current_seat = ($match->current_seat % GameRoom::MAX_PARTICIPANTS) + 1;
            $match->turn_number++;
            $targetId = null;
            foreach ($state['players'] as $participantId => $player) {
                if ((int) $player['seat'] === (int) $match->current_seat) {
                    $targetId = $participantId;
                    break;
                }
            }
            if ($targetId !== null && ($state['players'][$targetId]['blocked'] ?? false)) {
                $state['players'][$targetId]['blocked'] = false;
                $state['history'][] = ['id' => (string) Str::uuid(), 'participant_id' => (int) $targetId, 'message' => 'perdeu a vez por Merge Conflict.', 'turn' => $match->turn_number];

                continue;
            }

            $match->turn_started_at = now(); // relógio da nova vez (autoridade do servidor)

            return;
        }
    }

    /**
     * Embaralha com Fisher-Yates uniforme sobre uma fonte CRIPTOGRÁFICA (Random\Randomizer, motor seguro do PHP 8.2).
     * Nada do que o cliente envia entra no sorteio, e a ordem do baralho não é previsível a partir de jogadas anteriores.
     *
     * @param  list<string>  $cards
     * @return list<string>
     */
    private function shuffled(array $cards): array
    {
        return (new \Random\Randomizer)->shuffleArray(array_values($cards));
    }

    /** Quantas cartas do topo do baralho (já embaralhado) podem ser consideradas ao comprar. */
    public const DRAW_LOOKAHEAD = 8;

    private function draw(array &$state, array &$player, int $quantity, array $avoid = []): void
    {
        for ($i = 0; $i < $quantity; $i++) {
            if ($state['deck'] === [] && $state['discard'] !== []) {
                $state['deck'] = $this->shuffled($state['discard']);
                $state['discard'] = [];
            }
            if ($state['deck'] === []) {
                return;
            }
            $index = $this->pickIndex($state['deck'], $player['hand'], $avoid);
            $player['hand'][] = $state['deck'][$index];
            array_splice($state['deck'], $index, 1);
        }
    }

    /**
     * Qual carta do topo comprar. Percorre as primeiras DRAW_LOOKAHEAD cartas do baralho embaralhado e
     * fica com a PRIMEIRA aceitável: que não esteja em `$avoid` (ex.: a carta que o jogador acabou de
     * trocar) e que não forme uma trinca na mão. Se nenhuma serve, compra a do topo, como sempre.
     *
     * A escolha continua aleatória: a ordem do baralho já é sorteada e só se "pula" o que repetiria;
     * não se procura carta útil. A quantidade de cartas e todas as regras ficam intactas.
     *
     * @param  list<string>  $deck
     * @param  list<string>  $hand
     * @param  list<string>  $avoid
     */
    private function pickIndex(array $deck, array $hand, array $avoid): int
    {
        $counts = array_count_values($hand);
        $limit = min(count($deck), self::DRAW_LOOKAHEAD);
        for ($i = 0; $i < $limit; $i++) {
            $candidate = $deck[$i];
            if (in_array($candidate, $avoid, true) || ($counts[$candidate] ?? 0) >= 2) {
                continue;
            }

            return $i;
        }

        return 0;
    }

    private function removeCard(array &$hand, int $index): void
    {
        array_splice($hand, $index, 1);
    }

    private function history(array &$state, RoomParticipant $participant, string $message, GameMatch $match): void
    {
        $state['history'][] = ['id' => (string) Str::uuid(), 'participant_id' => $participant->id, 'message' => $message, 'turn' => $match->turn_number];
        $state['history'] = array_slice($state['history'], -30);
    }

    private function record(GameMatch $match, RoomParticipant $participant, string $action, array $payload, string $uuid): void
    {
        GameAction::create(['game_match_id' => $match->id, 'action_uuid' => $uuid, 'participant_id' => $participant->id, 'action' => $action, 'payload' => $payload, 'resulting_version' => $match->version, 'created_at' => now()]);
    }

    private function label(string $card): string
    {
        return ['git_login' => 'Git Login', 'git_clone' => 'git clone', 'git_checkout' => 'git checkout -b', 'git_pull' => 'git pull', 'git_add' => 'git add .', 'git_commit' => 'git commit', 'git_push' => 'git push', 'pull_request' => 'Pull Request', 'clear_credentials' => 'Limpar Credenciais', 'merge_conflict' => 'Merge Conflict'][$card] ?? $card;
    }
}
