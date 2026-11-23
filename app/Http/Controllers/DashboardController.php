<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Models\User;
use App\Services\RoomExpirationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $expiration = app(RoomExpirationService::class);

        // Complementa o scheduler: salas suas com prazo vencido são excluídas agora.
        $expiration->expireDueRoomsHostedBy($user->id);

        $rooms = GameRoom::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->withCount('participants')
            ->latest()
            ->limit(10)
            ->get(['id','host_user_id','status','created_at','started_at','empty_since'])
            ->map(fn (GameRoom $room) => [
                'id' => $room->id,
                'status' => $room->status,
                'participants_count' => $room->participants_count,
                'expires_at' => $room->status === 'waiting'
                    ? $expiration->deadline($room)?->toIso8601String()
                    : null,
                // A ação real é revalidada no servidor; isto só decide se o botão aparece.
                'can_delete' => $room->host_user_id === $user->id && $room->started_at === null,
            ]);

        return Inertia::render('Dashboard', [
            'rooms' => $rooms,
            // Avisos persistidos (ex.: sala excluída). Ao serem entregues viram
            // "lidos", então não reaparecem. A closure só roda quando incluída.
            'notices' => fn () => $this->takeNotices($user),
            'inviteUrl' => session('invite_url'),
            'recommendedPlayers' => User::query()
                ->orderByRaw("CASE WHEN LOWER(username) = 'gui' THEN 0 ELSE 1 END")
                ->inRandomOrder()
                ->limit(5)
                ->get(['username','avatar','profile_frame','profile_title'])
                ->sortBy(fn (User $user) => strtolower($user->username) === 'gui' ? 0 : 1)
                ->values()
                ->map(fn (User $user) => [
                    'username'=>$user->username,
                    'avatar'=>$user->avatar ? (str_starts_with($user->avatar, 'http') ? $user->avatar : Storage::disk('public')->url($user->avatar)) : null,
                    'profile_frame'=>$user->profile_frame,
                    'profile_title'=>$user->profile_title,
                ]),
        ]);
    }

    /** @return list<array{id: string, reason: string, message: string}> */
    private function takeNotices(User $user): array
    {
        $notifications = $user->unreadNotifications()->latest()->limit(5)->get();

        $notices = $notifications->map(fn ($notification) => [
            'id' => $notification->id,
            'reason' => (string) ($notification->data['reason'] ?? ''),
            'message' => (string) ($notification->data['message'] ?? ''),
        ])->all();

        $notifications->each->markAsRead();

        return $notices;
    }
}
