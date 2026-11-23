<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Models\RoomInvitation;
use App\Models\User;
use App\Notifications\RoomDeleted;
use App\Services\RoomExpirationService;
use App\Services\RoomStateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class GameRoomController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $result = DB::transaction(function () use ($request): array {
            // A sala nasce só com o anfitrião: a contagem de 5 minutos começa agora.
            $now = now();
            $room = new GameRoom(['host_user_id' => $request->user()->id, 'status' => 'waiting', 'empty_since' => $now]);
            $room->created_at = $now;
            $room->updated_at = $now;
            $room->save();
            $room->participants()->create(['user_id'=>$request->user()->id,'seat'=>1,'joined_at'=>now()]);
            return [$room, $this->issueInvitation($room)];
        });

        [$room, $token] = $result;
        return to_route('rooms.show', $room)->with('invite_url', route('rooms.join', $token));
    }

    public function show(Request $request, GameRoom $room): Response|RedirectResponse
    {
        abort_unless($room->participants()->where('user_id', $request->user()->id)->exists(), 403);

        // Complementa o scheduler: se o prazo venceu, a sala é excluída agora e
        // o aviso persistido aparece na Dashboard.
        if (app(RoomExpirationService::class)->expireIfEligible($room)) {
            return to_route('dashboard');
        }
        $room->refresh();
        $room->load(['host:id,username,avatar,profile_frame', 'participants.user:id,username,avatar,profile_frame']);

        return Inertia::render('Rooms/Show', [
            'room' => $room,
            'isHost' => $room->host_user_id === $request->user()->id,
            'inviteUrl' => session('invite_url'),
            'expiresAt' => app(RoomExpirationService::class)->deadline($room)?->toIso8601String(),
        ]);
    }

    public function invite(Request $request, GameRoom $room): RedirectResponse
    {
        abort_unless($room->host_user_id === $request->user()->id, 403);
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
        abort_unless($room->host_user_id === $request->user()->id, 403);
        $room->invitations()->whereNull('revoked_at')->update(['revoked_at'=>now()]);
        return back()->with('status', 'Convite revogado.');
    }

    public function cancel(Request $request, GameRoom $room): RedirectResponse
    {
        DB::transaction(function () use ($room, $request): void {
            $lockedRoom = GameRoom::query()->whereKey($room->id)->lockForUpdate()->first();
            // Já excluída (pelo scheduler ou por outra requisição): nada a fazer.
            abort_unless($lockedRoom, 404);
            abort_unless($lockedRoom->host_user_id === $request->user()->id, 403);
            // Uma partida em andamento ou encerrada guarda histórico: não some em silêncio.
            abort_if(
                $lockedRoom->started_at !== null,
                409,
                'Esta sala já teve uma partida e não pode ser excluída.'
            );

            $guestIds = $lockedRoom->participants()
                ->where('user_id', '!=', $lockedRoom->host_user_id)
                ->pluck('user_id')
                ->all();

            // Quem estava na sala é avisado, mesmo que esteja offline.
            Notification::send(
                User::query()->whereIn('id', $guestIds)->get(),
                new RoomDeleted($lockedRoom->id, RoomDeleted::HOST_DELETED)
            );

            // As FKs removem participantes e convites; nada mais referencia a sala.
            $lockedRoom->delete();

            $broadcaster = app(RoomStateBroadcaster::class);
            $broadcaster->publish($lockedRoom->id, 'deleted');
            foreach ([$lockedRoom->host_user_id, ...$guestIds] as $userId) {
                $broadcaster->publishToUser($userId);
            }
        }, 3);

        return to_route('dashboard')
            ->with('status', 'Sala excluída.')
            ->with('toast', [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'tone' => 'success',
                'message' => 'Sala excluída.',
            ]);
    }

    public function join(Request $request, string $token): RedirectResponse
    {
        $hash = hash('sha256', $token);
        $result = DB::transaction(function () use ($request, $hash): array {
            $found = RoomInvitation::query()->where('token_hash', $hash)->first();
            abort_if(!$found, 404, 'Convite inválido ou sala já excluída.');
            // Mesma ordem de bloqueio da expiração e da exclusão: sala, depois convite.
            $room = GameRoom::query()->whereKey($found->room_id)->lockForUpdate()->first();
            abort_if(!$room, 410, 'Esta sala foi excluída.');
            $invitation = RoomInvitation::query()->whereKey($found->id)->lockForUpdate()->first();
            abort_if(!$invitation, 410, 'Esta sala foi excluída.');
            abort_if($invitation->revoked_at, 410, 'Este convite foi revogado.');
            abort_if($invitation->expires_at->isPast(), 410, 'Este convite expirou.');
            if ($room->participants()->where('user_id', $request->user()->id)->exists()) {
                return [$room, false, false];
            }
            if (app(RoomExpirationService::class)->expireIfEligible($room)) {
                return [$room, true, false];
            }
            abort_if($room->status !== 'waiting', $room->status === 'expired' ? 410 : 409, 'A sala não está mais disponível.');
            $usedSeats = $room->participants()->pluck('seat')->all();
            $seat = collect(range(1, 4))->first(fn ($candidate) => !in_array($candidate, $usedSeats, true));
            abort_if(!$seat, 409, 'A sala está cheia.');
            $room->participants()->create(['user_id'=>$request->user()->id,'seat'=>$seat,'joined_at'=>now()]);
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
