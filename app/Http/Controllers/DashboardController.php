<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Models\RoomParticipant;
use App\Models\User;
use App\Notifications\RoomDeleted;
use App\Services\FinishedRoomCleanup;
use App\Services\RecommendedPlayers;
use App\Services\RoomExpirationService;
use App\Support\Bots;
use App\Support\PlayerPresenter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
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
        $recommended = app(RecommendedPlayers::class);

        // Complementa o scheduler: salas suas com prazo vencido são excluídas agora.
        $expiration->expireDueRoomsHostedBy($actor);
        // Salas com partida concluída há mais de 2 min saem agora, mesmo sem o scheduler.
        app(FinishedRoomCleanup::class)->purgeDueFor($actor);

        $rooms = GameRoom::query()
            ->whereIn('id', RoomParticipant::query()->forActor($actor)->select('room_id'))
            // Sala concluída não aceita mais ninguém: não aparece como disponível.
            ->where('status', '!=', 'finished')
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
            'botPreviews' => collect(Bots::pickRotating((int) config('bots.max_per_room')))
                ->map(fn (string $key) => PlayerPresenter::bot($key))
                ->values(),
            // Avisos persistidos (ex.: sala excluída). Ao serem entregues viram
            // "lidos", então não reaparecem. A closure só roda quando incluída.
            'notices' => fn () => $this->takeNotices($actor),
            'inviteUrl' => session('invite_url'),
            // Gui sempre primeiro; os demais por sorteio sem repetição e com rodízio entre
            // recarregamentos (ver RecommendedPlayers).
            'recommendedPlayers' => $this->recommendedWithBotFill($recommended->initial($actor)),
        ]);
    }

    /**
     * Pessoas de verdade primeiro. Só quando faltam jogadores para completar a lista, entram bots do catálogo
     * (sorteados com rodízio, sem repetir), SEMPRE marcados como bot, sem perfil público, sem seguir e sem ranking:
     * não se passam por pessoas reais e nunca contam como seguidores nem vitórias.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @return list<array<string, mixed>>
     */
    private function recommendedWithBotFill($users): array
    {
        $list = $users->map(fn (User $user) => RecommendedPlayers::present($user))->values()->all();
        $missing = RecommendedPlayers::INITIAL - count($list);
        foreach ($missing > 0 ? Bots::pickRotating($missing) : [] as $key) {
            $list[] = [...\Illuminate\Support\Arr::except(PlayerPresenter::bot($key), ['key']), 'is_bot' => true];
        }

        return $list;
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
