<?php

namespace App\Services;

use App\Models\GameRoom;
use App\Models\RoomParticipant;
use App\Support\Bots;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Fala com o Durable Object da partida (worker/src/match.ts). Com GAME_ENGINE=cloudflare
 * o estado do jogo vive lá; o Laravel mantém contas, salas, convites e o resultado final.
 * Toda chamada é assinada (HMAC) com o mesmo esquema do aviso de tempo real.
 */
class CloudflareMatchGateway
{
    public const ENGINE = 'cloudflare';

    /**
     * Versão da paridade comprovada entre o motor PHP e o do Worker (tests/parity/scenarios.json).
     * O motor da Cloudflare só liga com GAME_ENGINE=cloudflare E GAME_ENGINE_PARITY_ACK igual a este valor:
     * quem libera é uma pessoa, depois de ver os testes de paridade passando. Mudou uma regra? Regere a
     * fixture, rode os dois lados e suba esta versão (e o "parity_version" do JSON): a liberação antiga
     * deixa de valer sozinha.
     */
    public const PARITY_VERSION = '2026-11-26-2';

    public function __construct(private readonly CloudflareRealtime $realtime) {}

    public static function enabled(): bool
    {
        return config('realtime.game_engine') === self::ENGINE
            && config('realtime.engine_parity_ack') === self::PARITY_VERSION
            && CloudflareRealtime::enabled();
    }

    /** Cria a partida no Durable Object (idempotente: repetir devolve a mesma). */
    public function start(GameRoom $room): void
    {
        $participants = $room->participants()->with(['user', 'guest'])->orderBy('seat')->get();
        $taken = $participants->pluck('bot_key')->filter()->all();

        $this->send('POST', $room->id, 'start', [
            'room_id' => $room->id,
            'participants' => $participants->map(fn (RoomParticipant $p) => [
                'id' => $p->id, 'seat' => $p->seat, 'kind' => $p->kind, 'bot_key' => $p->bot_key,
            ])->values()->all(),
            'names' => $participants->mapWithKeys(fn (RoomParticipant $p) => [
                (string) $p->id => $p->kind === 'guest' ? $p->guest?->username : $p->user?->username,
            ])->filter()->all(),
            'turn_timeout_seconds' => (int) config('game.turn_timeout_seconds'),
            'bot_pool' => Bots::pick(10, $taken),
        ])->throw();
    }

    /** @return array<string, mixed> estado do ponto de vista do participante */
    public function view(string $roomId, int $participantId): array
    {
        return $this->send('GET', $roomId, 'view', null, '?participant='.$participantId)->throw()->json();
    }

    /** Devolve a resposta crua: o controller traduz 200/409/422 como o motor PHP faz. */
    public function act(string $roomId, int $participantId, array $validated): Response
    {
        return $this->send('POST', $roomId, 'act', [
            'participant_id' => $participantId,
            'action' => $validated['action'],
            'action_uuid' => $validated['action_uuid'],
            'version' => (int) $validated['version'],
            'card' => $validated['card'] ?? null,
            'target_id' => isset($validated['target_id']) ? (int) $validated['target_id'] : null,
        ]);
    }

    /** Um passo de bot no Durable Object (só joga se a versão enviada for a atual). Falha de rede não derruba a mesa. */
    public function botStep(string $roomId, int $version): bool
    {
        try {
            return (bool) $this->send('POST', $roomId, 'bots', ['version' => $version])->throw()->json('played');
        } catch (\Throwable) {
            return false;
        }
    }

    private function send(string $method, string $roomId, string $op, ?array $payload, string $query = ''): Response
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
        $request = Http::timeout(8)->withHeaders($this->realtime->signedHeaders($body));
        if ($method === 'GET') {
            return $request->retry(2, 150, throw: false)->get(config('realtime.worker_url')."/match/$roomId/$op$query");
        }

        return $request->withBody($body, 'application/json')->post(config('realtime.worker_url')."/match/$roomId/$op");
    }
}
