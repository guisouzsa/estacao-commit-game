<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * "Você desbloqueou uma conquista". Persistida na tabela de notificações (a mesma da central e do sino): quem estava
 * offline encontra o aviso ao voltar. É gravada na mesma transação da concessão e só existe se a conquista foi
 * realmente criada naquele momento (ver App\Services\Achievements::grant), então nunca duplica.
 */
class AchievementUnlocked extends Notification
{
    /** @param array<string, mixed> $definition item de config/achievements.php */
    public function __construct(public readonly array $definition) {}

    /** @param array<string, mixed> $definition */
    public static function for(array $definition): self
    {
        return new self($definition);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $d = $this->definition;

        return [
            'kind' => 'achievement',
            'slug' => $d['slug'],
            'name' => $d['name'],
            'description' => $d['description'],
            'rarity' => $d['rarity'],
            'tier' => $d['tier'],
            'icon' => $d['icon'],
            'message' => "Conquista desbloqueada: {$d['name']}.",
        ];
    }
}
