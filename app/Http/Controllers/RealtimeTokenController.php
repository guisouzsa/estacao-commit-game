<?php

namespace App\Http\Controllers;

use App\Models\RoomParticipant;
use App\Services\CloudflareRealtime;
use App\Support\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Emite o token de conexão do navegador ao Worker. A autorização é a MESMA de
 * routes/channels.php: só participantes da sala e o dono do canal pessoal.
 */
class RealtimeTokenController extends Controller
{
    public function __invoke(Request $request, CloudflareRealtime $realtime): JsonResponse
    {
        abort_unless(CloudflareRealtime::enabled(), 404);

        $channel = (string) $request->validate(['channel' => ['required', 'string', 'max:100']])['channel'];
        $actor = $request->user();

        $allowed = match (true) {
            (bool) preg_match('/^rooms\.[0-9a-fA-F-]{36}$/', $channel) => RoomParticipant::query()
                ->where('room_id', substr($channel, 6))->forActor($actor)->exists(),
            default => $channel === Actor::channel($actor),
        };
        abort_unless($allowed, 403);

        return response()->json([
            'url' => $realtime->socketUrl($channel),
            'token' => $realtime->connectionToken($channel, Actor::kind($actor).':'.Actor::id($actor)),
        ])->header('Cache-Control', 'no-store');
    }
}
