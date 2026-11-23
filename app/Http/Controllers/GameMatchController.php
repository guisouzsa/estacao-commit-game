<?php

namespace App\Http\Controllers;

use App\Models\GameAction;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Services\GameEngine;
use App\Services\GameInactivityService;
use App\Services\RoomStateBroadcaster;
use App\Support\PlayerPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            $match = $engine->create($locked);
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
            $engine->runBots($match, $room);
            $match->refresh();
            if ($wasActive && $match->status === 'finished') {
                $this->recordStatistics($room, $match);
            }
        }, 3);

        app(RoomStateBroadcaster::class)->publish($room->id, 'game_updated');

        return back();
    }

    private function recordStatistics(GameRoom $room, GameMatch $match): void
    {
        // O ciclo de vida da sala precisa terminar junto com a partida. A limpeza
        // de visitantes usa este estado para preservar apenas partidas em andamento.
        $room->forceFill([
            'status' => 'finished',
            'finished_at' => $match->finished_at ?? now(),
        ])->save();

        $room->participants()->with('user')->whereNotNull('user_id')->get()->each(function ($participant) use ($match): void {
            $user = $participant->user;
            $user->matches_played = ($user->matches_played ?? 0) + 1;
            if ($participant->id === $match->winner_participant_id) {
                $user->matches_won = ($user->matches_won ?? 0) + 1;
                $user->matches_lost ??= 0;
            } else {
                $user->matches_lost = ($user->matches_lost ?? 0) + 1;
                $user->matches_won ??= 0;
            }
            $user->save();
        });
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
                'history' => array_reverse(array_slice($state['history'], -12)),
            ],
            'participants' => $participants,
            'hand' => $viewer['hand'],
            'requiredCard' => GameEngine::REQUIRED[$viewer['step']] ?? null,
        ];
    }
}
