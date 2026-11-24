<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Services\MatchStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Callbacks Worker -> Laravel (partidas rodando no Durable Object). O Neon só recebe
 * o RESULTADO. Assinatura HMAC + janela de 60 s + nonce de uso único; e cada tipo é
 * idempotente, porque o Worker reenvia até receber 2xx.
 */
class RealtimeCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('realtime.callback_secret');
        abort_if($secret === '', 404);

        $body = $request->getContent();
        $timestamp = (string) $request->header('X-Timestamp');
        $nonce = (string) $request->header('X-Nonce');
        $expected = hash_hmac('sha256', "$timestamp.$nonce.".hash('sha256', $body), $secret);

        abort_unless(
            ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 60
            && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $nonce)
            && hash_equals($expected, (string) $request->header('X-Signature')),
            401,
        );
        // Mesmo pedido repetido dentro da janela: confirma sem reprocessar.
        if (! Cache::store(config('cache.limiter'))->add('rt-callback:'.$nonce, 1, 120)) {
            return response()->json(['replayed' => true]);
        }

        $event = json_decode($body, true);
        abort_unless(is_array($event) && is_string($event['room_id'] ?? null) && is_string($event['type'] ?? null), 422);

        match ($event['type']) {
            'match_finished' => $this->finished($event),
            'participant_replaced' => $this->replaced($event),
            default => abort(422),
        };

        return response()->json(['ok' => true]);
    }

    private function finished(array $event): void
    {
        DB::transaction(function () use ($event): void {
            $match = GameMatch::query()->where('room_id', $event['room_id'])->lockForUpdate()->first();
            if (! $match || $match->status === 'finished') {
                return; // sala excluída ou resultado já gravado
            }
            $room = GameRoom::query()->whereKey($event['room_id'])->lockForUpdate()->first();
            if (! $room) {
                return;
            }
            // O vencedor precisa ser um participante DESTA sala (nunca um id de outra partida).
            $winner = $event['winner_participant_id'] ?? null;
            abort_unless($winner === null || $room->participants()->whereKey($winner)->exists(), 422);
            $match->forceFill([
                'status' => 'finished',
                'winner_participant_id' => $event['winner_participant_id'] ?? null,
                'finished_at' => isset($event['finished_at']) ? Carbon::parse($event['finished_at']) : now(),
            ])->save();
            MatchStatistics::record($room, $match);
        }, 3);
    }

    private function replaced(array $event): void
    {
        $room = GameRoom::query()->find($event['room_id']);
        $participant = $room?->participants()->whereKey($event['participant_id'] ?? 0)->first();
        if (! $participant || $participant->isBot() || ! is_string($event['bot_key'] ?? null)) {
            return;
        }
        $participant->forceFill([
            'kind' => 'bot', 'bot_key' => $event['bot_key'], 'user_id' => null, 'guest_id' => null, 'is_host' => false,
        ])->save();
    }
}
