<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Models\RoomInvitation;
use App\Services\RoomExpirationService;
use App\Services\RoomStateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GameRoomController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $result = DB::transaction(function () use ($request): array {
            $room = GameRoom::create(['host_user_id' => $request->user()->id, 'status' => 'waiting']);
            $room->participants()->create(['user_id'=>$request->user()->id,'seat'=>1,'joined_at'=>now()]);
            return [$room, $this->issueInvitation($room)];
        });

        [$room, $token] = $result;
        return to_route('rooms.show', $room)->with('invite_url', route('rooms.join', $token));
    }

    public function show(Request $request, GameRoom $room): Response
    {
        abort_unless($room->participants()->where('user_id', $request->user()->id)->exists(), 403);
        app(RoomExpirationService::class)->expireIfEligible($room);
        $room->refresh();
        $room->load(['host:id,username,avatar,profile_frame', 'participants.user:id,username,avatar,profile_frame']);

        return Inertia::render('Rooms/Show', [
            'room' => $room,
            'isHost' => $room->host_user_id === $request->user()->id,
            'inviteUrl' => session('invite_url'),
            'expiresAt' => $room->created_at->copy()->addMinutes(RoomExpirationService::WAITING_MINUTES)->toIso8601String(),
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
        abort_if($expired, 410, 'Esta sala expirou.');
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
            $lockedRoom = GameRoom::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRoom->host_user_id === $request->user()->id, 403);
            abort_unless($lockedRoom->status === 'waiting', 409);
            abort_if(
                $lockedRoom->participants()
                    ->where('user_id', '!=', $lockedRoom->host_user_id)
                    ->exists(),
                409,
                'Não é possível excluir uma sala após a entrada de outro jogador.'
            );

            $lockedRoom->delete();
        }, 3);

        return to_route('dashboard')->with('status', 'Sala excluída.');
    }

    public function join(Request $request, string $token): RedirectResponse
    {
        $hash = hash('sha256', $token);
        $result = DB::transaction(function () use ($request, $hash): array {
            $invitation = RoomInvitation::query()->where('token_hash', $hash)->lockForUpdate()->first();
            abort_if(!$invitation, 404, 'Convite inválido.');
            abort_if($invitation->revoked_at, 410, 'Este convite foi revogado.');
            abort_if($invitation->expires_at->isPast(), 410, 'Este convite expirou.');
            $room = GameRoom::query()->whereKey($invitation->room_id)->lockForUpdate()->firstOrFail();
            if ($room->participants()->where('user_id', $request->user()->id)->exists()) {
                return [$room, false, false];
            }
            if (app(RoomExpirationService::class)->expireIfEligible($room)) {
                return [$room->fresh(), true, false];
            }
            abort_if($room->status !== 'waiting', $room->status === 'expired' ? 410 : 409, 'A sala não está mais disponível.');
            $usedSeats = $room->participants()->pluck('seat')->all();
            $seat = collect(range(1, 4))->first(fn ($candidate) => !in_array($candidate, $usedSeats, true));
            abort_if(!$seat, 409, 'A sala está cheia.');
            $room->participants()->create(['user_id'=>$request->user()->id,'seat'=>$seat,'joined_at'=>now()]);
            return [$room, false, true];
        }, 3);
        [$room, $expired, $joined] = $result;

        abort_if($expired, 410, 'Esta sala expirou.');
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
