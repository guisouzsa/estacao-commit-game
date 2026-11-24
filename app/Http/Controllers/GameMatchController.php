<?php

namespace App\Http\Controllers;

use App\Models\GameAction;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Services\CloudflareMatchGateway;
use App\Services\FinishedRoomCleanup;
use App\Services\GameEngine;
use App\Services\GameInactivityService;
use App\Services\MatchStatistics;
use App\Services\RoomStateBroadcaster;
use App\Support\PlayerPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GameMatchController extends Controller
{
    public function start(Request $request, GameRoom $room, GameEngine $engine): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($room->hasParticipant($actor), 404);
        abort_unless($room->isHostedBy($actor), 403);

        $match = DB::transaction(function () use ($room, $engine): GameMatch {
            $locked = GameRoom::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            if ($locked->gameMatch()->exists()) {
                return $locked->gameMatch;
            }
            abort_unless($locked->status === 'waiting', 409, 'Esta sala não pode iniciar outra partida.');
            $match = CloudflareMatchGateway::enabled() ? $this->createRemote($locked) : $engine->create($locked);
            $locked->forceFill(['status' => 'active', 'started_at' => now(), 'empty_since' => null])->save();

            return $match;
        }, 3);

        app(RoomStateBroadcaster::class)->publish($room->id, 'match_started');

        return to_route('games.show', $room);
    }

    public function show(Request $request, GameRoom $room): Response|RedirectResponse
    {
        $actor = $request->user();
        abort_unless($room->hasParticipant($actor), 404);
        // Partida concluída há mais de 2 minutos: a sala já deveria ter sumido.
        if (app(FinishedRoomCleanup::class)->purgeIfDue($room)) {
            return to_route('dashboard')->with('toast', [
                'id' => (string) Str::uuid(),
                'tone' => 'info',
                'title' => 'Sala encerrada',
                'message' => 'A partida terminou e a sala foi encerrada. O resultado ficou salvo no ranking.',
            ]);
        }
        if ($this->isRemote($room)) {
            return $this->showRemote($room, $actor);
        }
        // Jogador da vez sumiu? Um bot assume (ver GameInactivityService) e avisa a mesa.
        if (app(GameInactivityService::class)->sweep($room)) {
            app(RoomStateBroadcaster::class)->publish($room->id, 'game_updated');

            if (! $room->participants()->forActor($actor)->exists()) {
                return to_route('dashboard')->with('toast', [
                    'id' => (string) Str::uuid(),
                    'tone' => 'warning',
                    'title' => 'Um bot assumiu seu lugar',
                    'message' => 'Você ficou parado na sua vez por tempo demais e a partida seguiu sem você.',
                ]);
            }
        }
        $match = $room->gameMatch()->firstOrFail();
        $room->load(['participants.user', 'participants.guest']);
        $viewer = $room->participants()->forActor($actor)->firstOrFail();

        return Inertia::render('Games/Show', $this->present($room, $match, $viewer->id));
    }

    public function action(Request $request, GameRoom $room, GameEngine $engine): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($room->hasParticipant($actor), 404);
        $validated = $request->validate([
            'action_uuid' => ['required', 'uuid'],
            'action' => ['required', Rule::in(['play_card', 'play_block', 'exchange', 'discard_excess'])],
            'card' => ['nullable', 'string', 'max:40'],
            'target_id' => ['nullable', 'integer'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        if ($this->isRemote($room)) {
            return $this->actRemote($room, $actor, $validated);
        }

        DB::transaction(function () use ($room, $actor, $engine, $validated): void {
            $match = GameMatch::query()->where('room_id', $room->id)->lockForUpdate()->firstOrFail();
            if (GameAction::query()->where('game_match_id', $match->id)->where('action_uuid', $validated['action_uuid'])->exists()) {
                return;
            }
            if ((int) $validated['version'] !== (int) $match->version) {
                abort(409, 'A partida foi atualizada. Tente a jogada novamente.');
            }
            $participant = $room->participants()->forActor($actor)->firstOrFail();
            $wasActive = $match->status === 'active';
            $engine->act($match, $participant, $validated['action'], $validated, $validated['action_uuid']);
            $match->refresh();
            if ($wasActive && $match->status === 'finished') {
                $this->recordStatistics($room, $match);
            }
        }, 3);

        app(RoomStateBroadcaster::class)->publish($room->id, 'game_updated');

        return back();
    }

    /**
     * Um passo de bot: joga UMA vez se a vez é de um bot e o cliente viu a versão atual.
     * Vários clientes podem pedir ao mesmo tempo; só um passo acontece (os demais são ignorados
     * pela versão). Sem filas nem timers no servidor.
     */
    public function botStep(Request $request, GameRoom $room, GameEngine $engine): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($room->hasParticipant($actor), 404);
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        if ($this->isRemote($room)) {
            return back(); // no Durable Object os bots jogam lá dentro
        }

        $played = DB::transaction(function () use ($room, $engine, $validated): bool {
            $match = GameMatch::query()->where('room_id', $room->id)->lockForUpdate()->first();
            if (! $match || (int) $validated['version'] !== (int) $match->version) {
                return false;
            }
            $wasActive = $match->status === 'active';
            $played = $engine->botStep($match, $room);
            if ($played && $wasActive && $match->status === 'finished') {
                $this->recordStatistics($room, $match);
            }

            return $played;
        }, 3);

        if ($played) {
            app(RoomStateBroadcaster::class)->publish($room->id, 'game_updated');
        }

        return back();
    }

    private function recordStatistics(GameRoom $room, GameMatch $match): void
    {
        MatchStatistics::record($room, $match);
    }

    // ---- Partidas no Durable Object (GAME_ENGINE=cloudflare) -------------------------------

    /** Cada partida fica no motor em que nasceu: trocar a flag não órfã jogos em andamento. */
    private function isRemote(GameRoom $room): bool
    {
        $state = $room->gameMatch()->value('state');
        $state = is_string($state) ? json_decode($state, true) : $state;

        return ($state['engine'] ?? null) === CloudflareMatchGateway::ENGINE;
    }

    /** Registro leve no Neon: o estado do jogo vive no Durable Object; aqui fica o resultado. */
    private function createRemote(GameRoom $room): GameMatch
    {
        if ($room->participants()->count() !== GameRoom::MAX_PARTICIPANTS) {
            throw ValidationException::withMessages(['room' => 'A partida exige exatamente quatro participantes.']);
        }
        $match = GameMatch::create([
            'room_id' => $room->id, 'status' => 'active', 'current_seat' => 1, 'turn_number' => 1, 'version' => 1,
            'state' => ['engine' => CloudflareMatchGateway::ENGINE],
        ]);
        // Dentro da transação: se o Worker falhar, a sala não fica "iniciada" sem partida.
        app(CloudflareMatchGateway::class)->start($room);

        return $match;
    }

    private function showRemote(GameRoom $room, $actor): Response
    {
        $room->load(['participants.user', 'participants.guest']);
        $viewer = $room->participants()->forActor($actor)->firstOrFail();
        $remote = app(CloudflareMatchGateway::class)->view($room->id, $viewer->id);

        $byId = collect($remote['participants'])->keyBy('id');
        $participants = $room->participants->map(function ($participant) use ($byId, $viewer): array {
            $game = $byId[$participant->id];

            return [
                ...PlayerPresenter::participant($participant),
                'is_viewer' => $participant->id === $viewer->id,
                'hand_count' => $game['hand_count'],
                'path' => $game['path'],
                'step' => $game['step'],
                'blocked' => $game['blocked'],
                'pending_discard' => $game['pending_discard'],
            ];
        })->values();

        $game = $remote['game'];

        return Inertia::render('Games/Show', [
            'room' => ['id' => $room->id],
            'game' => [
                'id' => $room->gameMatch()->value('id'), 'status' => $game['status'], 'current_seat' => $game['current_seat'],
                'turn_number' => $game['turn_number'], 'version' => $game['version'],
                'winner_participant_id' => $game['winner_participant_id'],
                'deck_count' => $game['deck_count'], 'discard_count' => $game['discard_count'],
                'turn_seconds_left' => $game['turn_seconds_left'],
                'cleanup_at' => app(FinishedRoomCleanup::class)->deadline($room)?->toIso8601String(),
                'winner_wins' => $this->winnerWins($room, $game['winner_participant_id']),
                'history' => $game['history'],
            ],
            'participants' => $participants,
            'hand' => $remote['hand'],
            'requiredCard' => $remote['requiredCard'],
        ]);
    }

    private function actRemote(GameRoom $room, $actor, array $validated): RedirectResponse
    {
        $participant = $room->participants()->forActor($actor)->firstOrFail();
        $response = app(CloudflareMatchGateway::class)->act($room->id, $participant->id, $validated);

        if ($response->status() === 422) {
            throw ValidationException::withMessages($response->json('errors') ?? ['action' => 'Jogada inválida.']);
        }
        abort_if($response->status() === 409, 409, 'A partida foi atualizada. Tente a jogada novamente.');
        abort_unless($response->successful(), 503, 'Não foi possível registrar a jogada agora. Tente novamente.');

        return back();
    }

    /** Vitórias do vencedor no ranking geral (só contas; visitantes e bots não têm ranking). */
    private function winnerWins(GameRoom $room, ?int $winnerParticipantId): ?int
    {
        if ($winnerParticipantId === null) {
            return null;
        }

        $user = $room->participants->firstWhere('id', $winnerParticipantId)?->user;

        return $user ? (int) ($user->matches_won ?? 0) : null;
    }

    private function turnSecondsLeft(GameRoom $room, GameMatch $match): ?int
    {
        $timeout = (int) config('game.turn_timeout_seconds');
        if ($timeout <= 0 || $match->status !== 'active') {
            return null;
        }

        $current = $room->participants->firstWhere('seat', $match->current_seat);
        if (! $current || $current->isBot()) {
            return null;
        }

        return max(0, $timeout - (int) $match->updated_at->diffInSeconds(now(), true));
    }

    private function present(GameRoom $room, GameMatch $match, int $viewerId): array
    {
        $state = $match->state;
        $participants = $room->participants->map(function ($participant) use ($state, $viewerId): array {
            $game = $state['players'][(string) $participant->id];

            return [
                ...PlayerPresenter::participant($participant),
                'is_viewer' => $participant->id === $viewerId,
                'hand_count' => count($game['hand']),
                'path' => $game['path'],
                'step' => $game['step'],
                'blocked' => $game['blocked'],
                'pending_discard' => $game['pending_discard'],
            ];
        })->values();
        $viewer = $state['players'][(string) $viewerId];

        return [
            'room' => ['id' => $room->id],
            'game' => [
                'id' => $match->id, 'status' => $match->status, 'current_seat' => $match->current_seat,
                'turn_number' => $match->turn_number, 'version' => $match->version,
                'winner_participant_id' => $match->winner_participant_id,
                'deck_count' => count($state['deck']), 'discard_count' => count($state['discard']),
                // Tempo que o jogador da vez ainda tem antes de um bot assumir (ver GameInactivityService).
                // Só faz sentido para jogadores humanos em partida ativa.
                'turn_seconds_left' => $this->turnSecondsLeft($room, $match),
                'cleanup_at' => app(FinishedRoomCleanup::class)->deadline($room)?->toIso8601String(),
                'winner_wins' => $this->winnerWins($room, $match->winner_participant_id),
                'history' => array_reverse(array_slice($state['history'], -12)),
            ],
            'participants' => $participants,
            'hand' => $viewer['hand'],
            'requiredCard' => GameEngine::REQUIRED[$viewer['step']] ?? null,
        ];
    }
}
