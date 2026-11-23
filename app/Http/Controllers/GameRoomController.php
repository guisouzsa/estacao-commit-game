<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Models\RoomInvitation;
use App\Notifications\RoomDeleted;
use App\Services\RoomExpirationService;
use App\Services\RoomStateBroadcaster;
use App\Support\Actor;
use App\Support\Bots;
use App\Support\PlayerPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Salas. Quem age é sempre `$request->user()`: uma conta permanente ou um
 * visitante (guard "visitor"). Bots ocupam vagas, mas nunca chamam estas rotas.
 */
class GameRoomController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $validated = $request->validate([
            'bots' => ['nullable', 'integer', 'between:0,'.config('bots.max_per_room')],
        ], [
            'bots.between' => 'Escolha de 0 a '.config('bots.max_per_room').' bots.',
        ]);
        $botCount = (int) ($validated['bots'] ?? 0);

        $result = DB::transaction(function () use ($actor, $botCount): array {
            // A sala nasce só com o anfitrião humano: a contagem de 5 minutos começa agora.
            $now = now();
            $room = new GameRoom([
                'status' => 'waiting',
                'empty_since' => $now,
                'bot_slots' => $botCount,
                Actor::hostColumn($actor) => Actor::id($actor),
            ]);
            $room->created_at = $now;
            $room->updated_at = $now;
            $room->save();

            $room->participants()->create([
                Actor::participantColumn($actor) => Actor::id($actor),
                'kind' => Actor::kind($actor),
                'is_host' => true,
                'seat' => 1,
                'joined_at' => $now,
            ]);

            // Bots ocupam as últimas vagas (4, 3, 2), com nomes que não se repetem.
            foreach (Bots::pick($botCount) as $index => $botKey) {
                $room->participants()->create([
                    'kind' => 'bot',
                    'bot_key' => $botKey,
                    'is_host' => false,
                    'seat' => GameRoom::MAX_PARTICIPANTS - $index,
                    'joined_at' => $now,
                ]);
            }

            return [$room, $this->issueInvitation($room)];
        });

        [$room, $token] = $result;

        return to_route('rooms.show', $room)->with('invite_url', route('rooms.join', $token));
    }

    public function show(Request $request, GameRoom $room): Response|RedirectResponse
    {
        $actor = $request->user();

        // Sala de outra pessoa e sala inexistente respondem igual (404): não revela salas privadas.
        abort_unless($room->hasParticipant($actor), 404);

        // Complementa o scheduler: se o prazo venceu, a sala é excluída agora e
        // o aviso persistido aparece na Dashboard.
        if (app(RoomExpirationService::class)->expireIfEligible($room)) {
            return to_route('dashboard');
        }
        $room->refresh();
        $room->load(['participants.user', 'participants.guest']);

        return Inertia::render('Rooms/Show', [
            'room' => [
                'id' => $room->id,
                'status' => $room->status,
                'started_at' => $room->started_at,
                'bot_slots' => $room->bot_slots,
                'human_seats' => $room->humanSeats(),
                'participants' => $room->participants->map(fn ($participant) => PlayerPresenter::participant($participant))->values(),
            ],
            'isHost' => $room->isHostedBy($actor),
            'viewerKey' => PlayerPresenter::actor($actor)['key'],
            'inviteUrl' => session('invite_url'),
            'expiresAt' => app(RoomExpirationService::class)->deadline($room)?->toIso8601String(),
        ]);
    }

    public function invite(Request $request, GameRoom $room): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($room->hasParticipant($actor), 404);
        abort_unless($room->isHostedBy($actor), 403);
        $result = DB::transaction(function () use ($room): array {
            $lockedRoom = GameRoom::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            if ($lockedRoom->status !== 'waiting') {
                return [null, $lockedRoom->status === 'expired'];
            }
            if (app(RoomExpirationService::class)->expireIfEligible($lockedRoom)) {
                return [null, true];
            }

            $lockedRoom->invitations()->whereNull('revoked_at')->update(['revoked_at'=>now()]);

            return [$this->issueInvitation($lockedRoom), false];
        }, 3);

        [$token, $expired] = $result;
        abort_if($expired, 410, 'Esta sala foi excluída.');
        abort_if(!$token, 409, 'Esta sala não está mais disponível.');

        return back()->with('invite_url', route('rooms.join', $token));
    }

    public function revoke(Request $request, GameRoom $room): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($room->hasParticipant($actor), 404);
        abort_unless($room->isHostedBy($actor), 403);
        $room->invitations()->whereNull('revoked_at')->update(['revoked_at'=>now()]);
        return back()->with('status', 'Convite revogado.');
    }

    public function cancel(Request $request, GameRoom $room): RedirectResponse
    {
        $actor = $request->user();

        DB::transaction(function () use ($room, $actor): void {
            $lockedRoom = GameRoom::query()->whereKey($room->id)->lockForUpdate()->first();
            // Já excluída (pelo scheduler ou por outra requisição): nada a fazer.
            abort_unless($lockedRoom, 404);
            // Quem não participa não descobre que a sala existe.
            abort_unless($lockedRoom->hasParticipant($actor), 404);
            abort_unless($lockedRoom->isHostedBy($actor), 403);
            // Uma partida em andamento ou encerrada guarda histórico: não some em silêncio.
            abort_if(
                $lockedRoom->started_at !== null,
                409,
                'Esta sala já teve uma partida e não pode ser excluída.'
            );

            // Os outros humanos (contas e visitantes). Bots não recebem aviso.
            $guests = $lockedRoom->otherHumans()->with(['user', 'guest'])->get()
                ->map(fn ($participant) => $participant->user ?? $participant->guest)
                ->filter()
                ->values();

            // Quem estava na sala é avisado, mesmo que esteja offline.
            Notification::send($guests, new RoomDeleted($lockedRoom->id, RoomDeleted::HOST_DELETED));

            // As FKs removem participantes e convites; nada mais referencia a sala.
            $lockedRoom->delete();

            $broadcaster = app(RoomStateBroadcaster::class);
            $broadcaster->publish($lockedRoom->id, 'deleted');
            $broadcaster->publishToActor($actor);
            foreach ($guests as $guest) {
                $broadcaster->publishToActor($guest);
            }
        }, 3);

        return to_route('dashboard')
            ->with('status', 'Sala excluída.')
            ->with('toast', [
                'id' => (string) Str::uuid(),
                'tone' => 'success',
                'message' => 'Sala excluída.',
            ]);
    }

    public function join(Request $request, string $token): RedirectResponse
    {
        $actor = $request->user();
        $hash = hash('sha256', $token);
        $result = DB::transaction(function () use ($actor, $hash): array {
            $found = RoomInvitation::query()->where('token_hash', $hash)->first();
            abort_if(!$found, 404, 'Convite inválido ou sala já excluída.');
            // Mesma ordem de bloqueio da expiração e da exclusão: sala, depois convite.
            $room = GameRoom::query()->whereKey($found->room_id)->lockForUpdate()->first();
            abort_if(!$room, 410, 'Esta sala foi excluída.');
            $invitation = RoomInvitation::query()->whereKey($found->id)->lockForUpdate()->first();
            abort_if(!$invitation, 410, 'Esta sala foi excluída.');
            abort_if($invitation->revoked_at, 410, 'Este convite foi revogado.');
            abort_if($invitation->expires_at->isPast(), 410, 'Este convite expirou.');
            if ($room->hasParticipant($actor)) {
                return [$room, false, false];
            }
            if (app(RoomExpirationService::class)->expireIfEligible($room)) {
                return [$room, true, false];
            }
            abort_if($room->status !== 'waiting', $room->status === 'expired' ? 410 : 409, 'A sala não está mais disponível.');
            // Bots já ocupam as últimas vagas: sobra para humanos o que não está usado.
            $usedSeats = $room->participants()->pluck('seat')->all();
            $seat = collect(range(1, GameRoom::MAX_PARTICIPANTS))->first(fn ($candidate) => !in_array($candidate, $usedSeats, true));
            abort_if(!$seat, 409, 'A sala está cheia.');
            $room->participants()->create([
                Actor::participantColumn($actor) => Actor::id($actor),
                'kind' => Actor::kind($actor),
                'is_host' => false,
                'seat' => $seat,
                'joined_at' => now(),
            ]);
            // Entrou alguém além do anfitrião: a contagem de sala vazia é cancelada.
            app(RoomExpirationService::class)->syncEmptiness($room);
            return [$room, false, true];
        }, 3);
        [$room, $expired, $joined] = $result;

        abort_if($expired, 410, 'Esta sala foi excluída.');
        if ($joined) {
            app(RoomStateBroadcaster::class)->publish($room->id, 'participant_joined');
        }

        return to_route('rooms.show', $room)->with('status', 'Entrada na sala confirmada.');
    }

    private function issueInvitation(GameRoom $room): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $startsWithLetter = (bool) random_int(0, 1);
            $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            $numbers = '23456789';
            $code = '';
            for ($position = 0; $position < 5; $position++) {
                $useLetter = ($position % 2 === 0) === $startsWithLetter;
                $alphabet = $useLetter ? $letters : $numbers;
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $hash = hash('sha256', $code);
            if (RoomInvitation::query()->where('token_hash', $hash)->exists()) continue;
            $room->invitations()->create(['token_hash'=>$hash,'expires_at'=>now()->addHours(24)]);
            return $code;
        }
        throw new \RuntimeException('Não foi possível gerar um código de convite único.');
    }
}
