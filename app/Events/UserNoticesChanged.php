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

    /** @param  string  $userId  id da conta ou do visitante (nome mantido por compatibilidade) */
    public function __construct(public readonly string $userId, public readonly string $kind = 'user') {}

    public function broadcastOn(): array
    {
        $prefix = $this->kind === 'guest' ? 'App.Models.GuestProfile.' : 'App.Models.User.';

        return [new PrivateChannel($prefix.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.notices.changed';
    }
}
