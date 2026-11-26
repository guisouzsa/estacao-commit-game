<?php

namespace App\Support;

/** Fundos animados por nível de vitórias (config/profiles.php, `premium_background_tiers`). */
class PremiumBackgrounds
{
    /** @return array<string, string> todos os fundos animados (chave => nome) */
    public static function all(): array
    {
        $all = [];
        foreach ((array) config('profiles.premium_background_tiers') as $tier) {
            $all += (array) $tier['backgrounds'];
        }

        return $all;
    }

    /** Vitórias necessárias para o fundo, ou null se ele não é animado. */
    public static function minWins(string $key): ?int
    {
        foreach ((array) config('profiles.premium_background_tiers') as $tier) {
            if (array_key_exists($key, (array) $tier['backgrounds'])) {
                return (int) $tier['min_wins'];
            }
        }

        return null;
    }
}
