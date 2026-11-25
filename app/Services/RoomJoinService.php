<?php

namespace App\Services;

use App\Models\GameRoom;
use App\Models\RoomInvitation;
use App\Support\Actor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Entrada em salas por convite. Único lugar com essa regra: a rota de entrada, a
 * página do convite e o cadastro de visitante via convite usam o mesmo código,
 * então um perfil temporário nunca contorna as permissões de uma sala privada.
 */
class RoomJoinService
{
    public const OK = 'ok';

    public const INVALID = 'invalid';       // código desconhecido OU sala já excluída (indistinguíveis)

    public const REVOKED = 'revoked';

    public const EXPIRED = 'expired';       // convite vencido

    public const FULL = 'full';

    public const UNAVAILABLE = 'unavailable'; // sala fora do lobby (partida em andamento, encerrada)

    /** Códigos têm 5 caracteres; a comparação é sempre em maiúsculas. */
    public static function normalize(string $token): string
    {
        return strtoupper(trim($token));
    }

    /**
     * Só LÊ o estado do convite, sem entrar na sala nem reservar vaga. Serve para
     * mostrar a página certa antes de pedir qualquer coisa ao visitante.
     *
     * @return array{state: string, room: ?GameRoom, participants: int}
     */
    public function inspect(string $token): array
    {
        $invitation = RoomInvitation::query()
            ->where('token_hash', hash('sha256', self::normalize($token)))
            ->first();

        if (! $invitation) {
            return ['state' => self::INVALID, 'room' => null, 'participants' => 0];
        }

        if ($invitation->revoked_at) {
            return ['state' => self::REVOKED, 'room' => null, 'participants' => 0];
        }

        if ($invitation->expires_at->isPast()) {
            return ['state' => self::EXPIRED, 'room' => null, 'participants' => 0];
        }

        $room = GameRoom::query()->find($invitation->room_id);
        if (! $room) {
            return ['state' => self::INVALID, 'room' => null, 'participants' => 0];
        }

        // Sala vazia e vencida é excluída agora (o scheduler pode estar atrasado). A transação com trava só abre
        // quando o prazo pode ter vencido (checagem em memória); antes ela rodava em toda abertura de convite.
        $expiration = app(RoomExpirationService::class);
        if ($expiration->mightBeOverdue($room) && $expiration->expireIfEligible($room)) {
            return ['state' => self::INVALID, 'room' => null, 'participants' => 0];
        }

        if ($room->status !== 'waiting' || $room->started_at !== null) {
            return ['state' => self::UNAVAILABLE, 'room' => null, 'participants' => 0];
        }

        $participants = $room->participants()->count();
        if ($participants >= GameRoom::MAX_PARTICIPANTS) {
            return ['state' => self::FULL, 'room' => $room, 'participants' => $participants];
        }

        return ['state' => self::OK, 'room' => $room, 'participants' => $participants];
    }

    /**
     * Coloca o ator na sala. Com sucesso, avisa o lobby em tempo real.
     * Falhas viram HTTP exceptions com mensagem em português (o tratamento de erros
     * as mostra como aviso amigável, nunca como erro técnico).
     *
     * @return array{room: GameRoom, joined: bool} joined=false quando já estava na sala
     */
    public function join(Authenticatable $actor, string $token): array
    {
        $hash = hash('sha256', self::normalize($token));

        [$room, $expired, $joined] = DB::transaction(function () use ($actor, $hash): array {
            $found = RoomInvitation::query()->where('token_hash', $hash)->first();
            abort_if(! $found, 404, 'Não encontramos uma sala com esse código.');
            // Mesma ordem de bloqueio da expiração e da exclusão: sala, depois convite.
            $room = GameRoom::query()->whereKey($found->room_id)->lockForUpdate()->first();
            abort_if(! $room, 410, 'Esta sala foi excluída.');
            $invitation = RoomInvitation::query()->whereKey($found->id)->lockForUpdate()->first();
            abort_if(! $invitation, 410, 'Esta sala foi excluída.');
            abort_if($invitation->revoked_at, 410, 'Este convite foi revogado.');
            abort_if($invitation->expires_at->isPast(), 410, 'Este convite expirou.');

            if ($room->hasParticipant($actor)) {
                return [$room, false, false];
            }
            $expiration = app(RoomExpirationService::class);
            if ($expiration->mightBeOverdue($room) && $expiration->expireIfEligible($room)) {
                return [$room, true, false];
            }
            abort_if($room->status !== 'waiting', $room->status === 'expired' ? 410 : 409, 'A sala não está mais disponível.');

            // Bots já ocupam as últimas vagas: sobra para humanos o que não está usado.
            $usedSeats = $room->participants()->pluck('seat')->all();
            $seat = collect(range(1, GameRoom::MAX_PARTICIPANTS))
                ->first(fn ($candidate) => ! in_array($candidate, $usedSeats, true));
            abort_if(! $seat, 409, 'A sala está cheia.');

            $room->participants()->create([
                Actor::participantColumn($actor) => Actor::id($actor),
                'kind' => Actor::kind($actor),
                'is_host' => false,
                'seat' => $seat,
                'joined_at' => now(),
            ]);
            // Entrou um humano que não é o anfitrião: a sala deixou de estar "vazia" e a contagem de 5 minutos para.
            // (Mesmo efeito de syncEmptiness, sem a consulta extra: aqui já se sabe que há outro humano.)
            if ($room->empty_since !== null) {
                $room->forceFill(['empty_since' => null])->save();
            }

            return [$room, false, true];
        }, 3);

        abort_if($expired, 410, 'Esta sala foi excluída.');

        if ($joined) {
            app(RoomStateBroadcaster::class)->publish($room->id, 'participant_joined');
        }

        return ['room' => $room, 'joined' => $joined];
    }
}
