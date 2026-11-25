<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\RoomParticipant;
use App\Support\OpsLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Evita que a mesa trave quando um humano some na própria vez. O relógio é do SERVIDOR
 * (`game_matches.turn_started_at`), nunca do navegador.
 *
 * Duas portas de entrada, que fazem exatamente a mesma coisa sob trava de linha (sem contar nada em dobro):
 *  - `sweep($room)`: quando alguém da mesa abre/recarrega a partida;
 *  - `sweepStalled()`: comando `games:sweep`, a cada minuto, para mesas com TODAS as páginas fechadas.
 *
 * Se a vez de um humano passou de `game.turn_timeout_seconds`:
 *  1. a vez é PULADA e a ocorrência é contada em `room_participants.timeout_streak`;
 *  2. uma jogada válida zera a contagem (ver GameEngine::act);
 *  3. no `game.max_consecutive_timeouts`-ésimo timeout seguido o jogador é removido da sala: um bot
 *     assume o lugar (mesma mão e percurso) e a partida segue para os demais. Sem humanos, a sala some.
 * Se quem saiu era o anfitrião, o comando passa ao humano que entrou logo depois dele.
 * Vezes de bot paradas (ninguém pediu o passo) são jogadas pelo servidor em `sweepStalled()`.
 */
class GameInactivityService
{
    /** Quantas vezes seguidas uma única chamada processa (mesa parada por vários prazos). */
    private const MAX_STEPS = 4;

    /** Passos de bot por mesa e por execução (evita laço longo dentro da transação). */
    private const MAX_BOT_STEPS = 12;

    public function __construct(private readonly GameEngine $engine) {}

    public static function deadline(GameMatch $match): ?Carbon
    {
        $timeout = (int) config('game.turn_timeout_seconds');
        if ($timeout <= 0 || $match->status !== 'active') {
            return null;
        }

        return ($match->turn_started_at ?? $match->updated_at)?->copy()->addSeconds($timeout);
    }

    /** @return bool true quando a mesa mudou (vez pulada, jogador removido ou sala encerrada). */
    public function sweep(GameRoom $room): bool
    {
        $changed = false;
        for ($i = 0; $i < self::MAX_STEPS; $i++) {
            if (! $this->sweepOnce($room)) {
                break;
            }
            $changed = true;
            if (! GameRoom::query()->whereKey($room->id)->exists()) {
                break; // a sala foi destruída (só sobraram bots)
            }
        }

        return $changed;
    }

    /**
     * Varredura de TODAS as mesas paradas. Uma consulta indexada (status + relógio da vez) traz só o que passou do
     * prazo. Só o motor PHP: partidas do Worker têm o próprio alarme no Durable Object.
     *
     * @return array{checked: int, timeouts: int, bot_steps: int}
     */
    public function sweepStalled(): array
    {
        $timeout = (int) config('game.turn_timeout_seconds');
        $stall = (int) config('game.bot_stall_seconds');
        $limits = array_filter([$timeout, $stall], fn (int $seconds) => $seconds > 0);
        $result = ['checked' => 0, 'timeouts' => 0, 'bot_steps' => 0];
        if ($limits === []) {
            return $result;
        }

        $matches = GameMatch::query()
            ->where('status', 'active')
            ->whereNotNull('turn_started_at')
            ->where('turn_started_at', '<=', now()->subSeconds(min($limits)))
            ->orderBy('turn_started_at')
            ->limit(max(1, (int) config('game.sweep_batch')))
            ->get(['id', 'room_id', 'current_seat', 'turn_started_at', 'state']);

        foreach ($matches as $match) {
            if (($match->state['engine'] ?? null) === CloudflareMatchGateway::ENGINE) {
                continue;
            }
            $room = GameRoom::query()->find($match->room_id);
            if (! $room) {
                continue;
            }
            $result['checked']++;
            $current = $room->participants()->where('seat', $match->current_seat)->first();

            if ($current && ! $current->isBot()) {
                if ($this->sweep($room)) {
                    $result['timeouts']++;
                    app(RoomStateBroadcaster::class)->publish($room->id, 'game_updated');
                }

                continue;
            }

            $steps = $this->playStalledBots($room, $stall);
            if ($steps > 0) {
                $result['bot_steps'] += $steps;
                OpsLog::event('game.bot_stalled', ['room' => $room->id, 'steps' => $steps]);
                app(RoomStateBroadcaster::class)->publish($room->id, 'game_updated');
            }
        }

        return $result;
    }

    /** Joga as vezes de bot que ficaram esperando. Para quando chega a vez de um humano ou a partida acaba. */
    private function playStalledBots(GameRoom $room, int $stall): int
    {
        return DB::transaction(function () use ($room, $stall): int {
            $match = GameMatch::query()->where('room_id', $room->id)->lockForUpdate()->first();
            if (! $match || $match->status !== 'active' || ! $match->turn_started_at
                || $match->turn_started_at->copy()->addSeconds(max(1, $stall))->isFuture()) {
                return 0;
            }

            $steps = 0;
            while ($steps < self::MAX_BOT_STEPS && $this->engine->botStep($match, $room)) {
                $steps++;
            }
            if ($steps > 0 && $match->status === 'finished') {
                MatchStatistics::record($room, $match);
            }

            return $steps;
        }, 3);
    }

    private function sweepOnce(GameRoom $room): bool
    {
        return DB::transaction(function () use ($room): bool {
            $match = GameMatch::query()->where('room_id', $room->id)->lockForUpdate()->first();
            $deadline = $match ? self::deadline($match) : null;
            if (! $match || ! $deadline || $deadline->isFuture()) {
                return false;
            }

            $current = $room->participants()->where('seat', $match->current_seat)->first();
            if (! $current || $current->isBot()) {
                return false; // vez de bot: não tem prazo de humano (ver playStalledBots)
            }

            $streak = (int) $current->timeout_streak + 1;
            $limit = max(1, (int) config('game.max_consecutive_timeouts'));
            OpsLog::event('game.turn_timeout', ['room' => $room->id, 'participant' => $current->id, 'streak' => $streak, 'limit' => $limit]);

            if ($streak >= $limit) {
                OpsLog::event('game.player_inactive', ['room' => $room->id, 'participant' => $current->id, 'timeouts' => $streak]);
                $wasHost = (bool) $current->is_host;
                $destroyed = app(RoomLeaveService::class)->replaceWithBot(
                    $room,
                    $current,
                    "ficou inativo por {$limit} vezes seguidas e saiu da sala. Um bot assumiu o lugar.",
                );
                if (! $destroyed && $wasHost) {
                    app(RoomLeaveService::class)->transferHostFrom($room);
                    OpsLog::event('game.host_transferred', ['room' => $room->id, 'reason' => 'inactivity']);
                }
                if (! $destroyed) {
                    $match->refresh();
                    $match->forceFill(['turn_started_at' => now()])->save(); // o bot começa a vez agora
                }

                return true;
            }

            // Vez pulada: conta o timeout e passa para o próximo jogador.
            RoomParticipant::query()->whereKey($current->id)->update(['timeout_streak' => $streak]);
            $state = $match->state;
            $state['history'][] = [
                'id' => (string) Str::uuid(),
                'participant_id' => $current->id,
                'message' => "perdeu a vez por tempo esgotado ({$streak}/{$limit}).",
                'turn' => $match->turn_number,
            ];
            $match->state = $state;
            $this->engine->skipTurn($match);
            $match->version++;
            $match->save();

            return true;
        }, 3);
    }
}
