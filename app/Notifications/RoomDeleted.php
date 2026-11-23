<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso persistido de que uma sala deixou de existir. É gravado na mesma
 * transação da exclusão, então não se perde se o usuário estiver offline.
 */
class RoomDeleted extends Notification
{
    public const EMPTY_TIMEOUT = 'empty_timeout';

    public const HOST_DELETED = 'host_deleted';

    public function __construct(
        public readonly string $roomId,
        public readonly string $reason,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'room_id' => $this->roomId,
            'reason' => $this->reason,
            'message' => $this->message(),
        ];
    }

    public function message(): string
    {
        return match ($this->reason) {
            self::EMPTY_TIMEOUT => 'A sala foi excluída automaticamente porque permaneceu vazia por 5 minutos.',
            default => 'O criador excluiu a sala '.substr($this->roomId, 0, 8).'.',
        };
    }
}
