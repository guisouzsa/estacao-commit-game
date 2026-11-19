<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Models\RoomInvitation;
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
        $room->load(['host:id,username,avatar,profile_frame', 'participants.user:id,username,avatar,profile_frame']);

        return Inertia::render('Rooms/Show', [
            'room' => $room,
            'isHost' => $room->host_user_id === $request->user()->id,
            'inviteUrl' => session('invite_url'),
        ]);
    }

    public function invite(Request $request, GameRoom $room): RedirectResponse
    {
        abort_unless($room->host_user_id === $request->user()->id && $room->status === 'waiting', 403);
        $room->invitations()->whereNull('revoked_at')->update(['revoked_at'=>now()]);
        $token = $this->issueInvitation($room);
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
        abort_unless($room->host_user_id === $request->user()->id, 403);
        abort_unless($room->status === 'waiting', 409);
        DB::transaction(function () use ($room): void {
            GameRoom::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $room->invitations()->whereNull('revoked_at')->update(['revoked_at'=>now()]);
            $room->update(['status'=>'cancelled']);
        });
        return to_route('dashboard')->with('status', 'Sala cancelada.');
    }

    public function join(Request $request, string $token): RedirectResponse
    {
        $hash = hash('sha256', $token);
        $room = DB::transaction(function () use ($request, $hash): GameRoom {
            $invitation = RoomInvitation::query()->where('token_hash', $hash)->lockForUpdate()->first();
            abort_if(!$invitation, 404, 'Convite inválido.');
            abort_if($invitation->revoked_at, 410, 'Este convite foi revogado.');
            abort_if($invitation->expires_at->isPast(), 410, 'Este convite expirou.');
            $room = GameRoom::query()->whereKey($invitation->room_id)->lockForUpdate()->firstOrFail();
            abort_if($room->status !== 'waiting', 409, 'A sala não está mais disponível.');
            if ($room->participants()->where('user_id', $request->user()->id)->exists()) return $room;
            $usedSeats = $room->participants()->pluck('seat')->all();
            $seat = collect(range(1, 4))->first(fn ($candidate) => !in_array($candidate, $usedSeats, true));
            abort_if(!$seat, 409, 'A sala está cheia.');
            $room->participants()->create(['user_id'=>$request->user()->id,'seat'=>$seat,'joined_at'=>now()]);
            return $room;
        }, 3);
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
