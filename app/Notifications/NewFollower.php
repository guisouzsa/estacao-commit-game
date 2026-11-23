<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\PlayerPresenter;
use Illuminate\Notifications\Notification;

/**
 * "Alguém começou a seguir você". É um aviso para consultar depois (central de
 * notificações), diferente dos eventos passageiros de sala, que não são salvos.
 */
class NewFollower extends Notification
{
    public function __construct(
        public readonly string $followerUsername,
        public readonly string $followerSeed,
    ) {}

    public static function from(User $follower): self
    {
        return new self($follower->username, PlayerPresenter::seed((string) $follower->id));
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'username' => $this->followerUsername,
            'avatar_seed' => $this->followerSeed,
            'message' => "@{$this->followerUsername} começou a seguir você.",
        ];
    }
}
