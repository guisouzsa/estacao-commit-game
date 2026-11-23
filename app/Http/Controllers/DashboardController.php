<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Models\RoomParticipant;
use App\Models\User;
use App\Notifications\RoomDeleted;
use App\Services\RoomExpirationService;
use App\Support\Bots;
use App\Support\PlayerPresenter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard de quem joga: conta permanente ou visitante (guard "visitor").
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $actor = $request->user();
        $expiration = app(RoomExpirationService::class);

        // Complementa o scheduler: salas suas com prazo vencido são excluídas agora.
        $expiration->expireDueRoomsHostedBy($actor);

        $rooms = GameRoom::query()
            ->whereIn('id', RoomParticipant::query()->forActor($actor)->select('room_id'))
            ->withCount('participants')
            ->latest()
            ->limit(10)
            ->get(['id', 'host_user_id', 'host_guest_id', 'status', 'created_at', 'started_at', 'empty_since', 'bot_slots'])
            ->map(fn (GameRoom $room) => [
                'id' => $room->id,
                'status' => $room->status,
                'participants_count' => $room->participants_count,
                'bot_slots' => $room->bot_slots,
                'expires_at' => $room->status === 'waiting'
                    ? $expiration->deadline($room)?->toIso8601String()
                    : null,
                // A ação real é revalidada no servidor; isto só decide se o botão aparece.
                'can_delete' => $room->isHostedBy($actor) && $room->started_at === null,
            ]);

        return Inertia::render('Dashboard', [
            'rooms' => $rooms,
            // Identidades reais do catálogo usadas apenas na prévia. A criação
            // continua sorteando e validando bots exclusivamente no servidor.
            'botPreviews' => collect(array_keys(Bots::all()))
                ->take((int) config('bots.max_per_room'))
                ->map(fn (string $key) => PlayerPresenter::bot($key))
                ->values(),
            // Avisos persistidos (ex.: sala excluída). Ao serem entregues viram
            // "lidos", então não reaparecem. A closure só roda quando incluída.
            'notices' => fn () => $this->takeNotices($actor),
            'inviteUrl' => session('invite_url'),
            'recommendedPlayers' => User::query()
                ->orderByRaw("CASE WHEN LOWER(username) = 'gui' THEN 0 ELSE 1 END")
                ->inRandomOrder()
                ->limit(5)
                ->get(['id', 'username', 'avatar', 'profile_frame', 'profile_title'])
                ->sortBy(fn (User $user) => strtolower($user->username) === 'gui' ? 0 : 1)
                ->values()
                ->map(fn (User $user) => [
                    // Sem `key` (que carrega o ID): a Dashboard só precisa exibir o jogador.
                    ...Arr::except(PlayerPresenter::user($user), ['key']),
                    'profile_title' => $user->profile_title,
                ]),
        ]);
    }

    /** @return list<array{id: string, reason: string, message: string}> */
    private function takeNotices(Authenticatable $actor): array
    {
        $notifications = $actor->unreadNotifications()
            ->where('type', RoomDeleted::class)
            ->latest()
            ->limit(5)
            ->get();

        $notices = $notifications->map(fn ($notification) => [
            'id' => $notification->id,
            'reason' => (string) ($notification->data['reason'] ?? ''),
            'message' => (string) ($notification->data['message'] ?? ''),
        ])->all();

        $notifications->each->markAsRead();

        return $notices;
    }
}
