<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Apenas avisa a Dashboard do usuário que há algo novo (salas ou avisos).
 * O conteúdo continua vindo do servidor quando o front recarrega.
 */
class UserNoticesChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly string $userId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.notices.changed';
    }
}
