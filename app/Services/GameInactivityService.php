<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\RoomParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Evita que a mesa trave quando um humano some na própria vez. O relógio é do SERVIDOR
 * (`game_matches.turn_started_at`), nunca do navegador.
 *
 * Sem processo extra: a verificação roda quando alguém da mesa abre/recarrega a partida (o front
 * sincroniza de tempos em tempos). Se a vez de um humano passou de `game.turn_timeout_seconds`:
 *  1. a vez é PULADA e a ocorrência é contada em `room_participants.timeout_streak`;
 *  2. uma jogada válida zera a contagem (ver GameEngine::act);
 *  3. no `game.max_consecutive_timeouts`-ésimo timeout seguido o jogador é removido da sala: um bot
 *     assume o lugar (mesma mão e percurso) e a partida segue para os demais. Sem humanos, a sala some.
 * Se quem saiu era o anfitrião, o comando passa ao humano que entrou logo depois dele.
 */
class GameInactivityService
{
    /** Quantas vezes seguidas uma única chamada processa (mesa parada por vários prazos). */
    private const MAX_STEPS = 4;

    public function __construct(private readonly GameEngine $engine) {}

    public static function deadline(GameMatch $match): ?\Illuminate\Support\Carbon
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
                return false; // vez de bot: quem dá o ritmo é o cliente (botStep)
            }

            $streak = (int) $current->timeout_streak + 1;
            $limit = max(1, (int) config('game.max_consecutive_timeouts'));
            Log::info('game.timeout', ['room' => $room->id, 'participant' => $current->id, 'streak' => $streak, 'limit' => $limit]);

            if ($streak >= $limit) {
                $wasHost = (bool) $current->is_host;
                $destroyed = app(RoomLeaveService::class)->replaceWithBot(
                    $room,
                    $current,
                    "ficou inativo por {$limit} vezes seguidas e saiu da sala. Um bot assumiu o lugar.",
                );
                if (! $destroyed && $wasHost) {
                    app(RoomLeaveService::class)->transferHostFrom($room);
                    Log::info('game.host_transferred', ['room' => $room->id, 'reason' => 'inactivity']);
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
