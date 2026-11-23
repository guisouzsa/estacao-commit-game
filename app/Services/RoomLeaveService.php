<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\RoomParticipant;
use App\Support\Bots;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sair de uma sala (lobby ou partida), incluindo o anfitrião.
 *
 * Regras:
 * - Só restariam bots? A sala se autodestrói (ninguém jogaria).
 * - Ainda há ao menos um humano (ex.: 2 bots + 1 pessoa, ou 1 bot + 2 pessoas)? A sala continua.
 * - No lobby, quem sai libera a vaga. Numa partida em andamento, um bot assume o lugar (mesma mão
 *   e mesmo percurso), e a partida segue pelo mesmo motor de regras.
 * - Se quem sai é o anfitrião, o comando passa ao humano mais antigo da sala.
 * - Partida já encerrada: nada muda (o histórico e as vitórias ficam como estão).
 */
class RoomLeaveService
{
    public const LEFT = 'left';

    public const ROOM_CLOSED = 'room_closed';

    public const NOTHING = 'nothing';

    public function __construct(private readonly RoomExpirationService $expiration, private readonly GameEngine $engine) {}

    /** @return self::LEFT|self::ROOM_CLOSED|self::NOTHING */
    public function leave(GameRoom $room, Authenticatable $actor): string
    {
        $result = DB::transaction(function () use ($room, $actor): string {
            $locked = GameRoom::query()->whereKey($room->id)->lockForUpdate()->first();
            abort_unless($locked, 404);
            $leaving = $locked->participants()->forActor($actor)->first();
            abort_unless($leaving, 404);

            if ($locked->status === 'finished') {
                return self::NOTHING;
            }

            $humansLeft = $locked->participants()
                ->where('kind', '!=', 'bot')
                ->where('id', '!=', $leaving->id)
                ->orderBy('joined_at')->orderBy('id')
                ->get();

            if ($humansLeft->isEmpty()) {
                $this->destroy($locked);

                return self::ROOM_CLOSED;
            }

            $wasHost = (bool) $leaving->is_host;

            if ($locked->started_at !== null) {
                $this->replaceWithBot($locked, $leaving, 'abandonou a partida. Um bot assumiu o lugar.');
            } else {
                $leaving->delete();
                $this->expiration->syncEmptiness($locked);
            }

            if ($wasHost) {
                $this->transferHost($locked, $humansLeft->first());
            }

            return self::LEFT;
        }, 3);

        $broadcaster = app(RoomStateBroadcaster::class);
        match ($result) {
            self::ROOM_CLOSED => $broadcaster->publishToActor($actor),
            self::LEFT => $broadcaster->publish($room->id, 'participant_left'),
            default => null,
        };

        return $result;
    }

    /**
     * Um humano some e um bot ocupa o MESMO lugar (mesmo id: a mão e o percurso ficam).
     * Usado ao abandonar e pela inatividade. Se não restar nenhum humano, a sala é destruída.
     *
     * @return bool true se a sala foi destruída
     */
    public function replaceWithBot(GameRoom $room, RoomParticipant $participant, string $message): bool
    {
        $participant->loadMissing(['user', 'guest']);
        $username = $participant->user?->username ?? $participant->guest?->username;
        $taken = $room->participants()->whereNotNull('bot_key')->pluck('bot_key')->all();
        $botKey = Bots::pick(1, $taken)[0] ?? null;
        abort_if($botKey === null, 409, 'Não há bots disponíveis para assumir o lugar.');

        $participant->forceFill([
            'kind' => 'bot', 'bot_key' => $botKey, 'user_id' => null, 'guest_id' => null, 'is_host' => false,
        ])->save();

        $humans = $room->participants()->where('kind', '!=', 'bot')->count();
        if ($humans === 0) {
            $this->destroy($room);

            return true;
        }

        $match = GameMatch::query()->where('room_id', $room->id)->lockForUpdate()->first();
        if ($match && $match->status === 'active') {
            $state = $match->state;
            $state['history'][] = [
                'id' => (string) Str::uuid(),
                'message' => ($username ? '@'.$username.' ' : 'Um jogador ').$message,
                'turn' => $match->turn_number,
                'notify' => true,
            ];
            $match->state = $state;
            $match->version++;
            $match->save();
            $this->engine->runBots($match->refresh(), $room);
        }

        return false;
    }

    private function transferHost(GameRoom $room, RoomParticipant $next): void
    {
        // Exatamente UMA das colunas de anfitrião fica preenchida (restrição do banco).
        $room->forceFill([
            'host_user_id' => $next->user_id,
            'host_guest_id' => $next->guest_id,
        ])->save();
        $room->participants()->update(['is_host' => false]);
        $next->forceFill(['is_host' => true])->save();
    }

    /** Sala sem humanos: some, junto com participantes, convites e partida (FKs em cascata). */
    private function destroy(GameRoom $room): void
    {
        $room->delete();
        app(RoomStateBroadcaster::class)->publish($room->id, 'deleted');
    }
}
