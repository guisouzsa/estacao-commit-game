<?php

namespace App\Services;

use App\Events\RoomStateChanged;
use App\Events\UserNoticesChanged;
use App\Support\Actor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RoomStateBroadcaster
{
    public function __construct(private Dispatcher $events) {}

    public function publish(string $roomId, string $change): void
    {
        $this->afterCommit(
            new RoomStateChanged($roomId, $change),
            ['room_id' => $roomId, 'change' => $change],
        );
    }

    /** Avisa a Dashboard do usuário de que há salas ou avisos novos. */
    public function publishToUser(string $userId): void
    {
        $this->afterCommit(new UserNoticesChanged($userId), ['user_id' => $userId]);
    }

    /** Igual a publishToUser, mas para uma conta OU um visitante. */
    public function publishToActor(Authenticatable $actor): void
    {
        $id = Actor::id($actor);

        $this->afterCommit(new UserNoticesChanged($id, Actor::kind($actor)), ['actor_id' => $id]);
    }

    /**
     * O evento só sai depois do commit e nunca derruba a operação principal:
     * o estado já está salvo e o polling cobre quem não receber o aviso.
     *
     * @param  array<string, string>  $context
     */
    private function afterCommit(object $event, array $context): void
    {
        DB::afterCommit(function () use ($event, $context): void {
            try {
                if (CloudflareRealtime::enabled() && $event instanceof \Illuminate\Contracts\Broadcasting\ShouldBroadcast) {
                    $this->sendToWorker($event);

                    return;
                }
                $this->events->dispatch($event);
            } catch (Throwable $exception) {
                Log::warning('Room state broadcast failed; polling remains available.', [
                    ...$context,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    /** Mesmo canal, mesmo nome de evento e mesmo corpo que o Reverb entregaria. */
    private function sendToWorker(object $event): void
    {
        $data = get_object_vars($event);
        $worker = app(CloudflareRealtime::class);
        foreach ($event->broadcastOn() as $channel) {
            $worker->notify(preg_replace('/^private-/', '', $channel->name), $event->broadcastAs(), $data);
        }
    }
}
