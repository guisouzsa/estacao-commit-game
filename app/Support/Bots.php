<?php

namespace App\Support;

/** Catálogo de bots (config/bots.php). Bots não são usuários. */
class Bots
{
    /** @return array<string, array{key: string, name: string, style: string}> */
    public static function all(): array
    {
        static $indexed = null;

        return $indexed ??= collect(config('bots.catalog'))->keyBy('key')->all();
    }

    /** @return array{key: string, name: string, style: string}|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Sorteio com RODÍZIO: bots que acabaram de aparecer para esta pessoa só voltam depois dos que ainda
     * não apareceram. Sem repetição dentro da lista e sem depender da ordem do catálogo (que é embaralhada).
     *
     * @return list<string> chaves dos bots
     */
    public static function pickRotating(int $count, array $exclude = []): array
    {
        $recent = (array) session('bots.recent', []);
        $available = array_values(array_diff(array_keys(self::all()), $exclude));
        shuffle($available);
        $fresh = array_values(array_diff($available, $recent));
        $stale = array_values(array_filter($recent, fn ($key) => in_array($key, $available, true)));
        $chosen = array_slice([...$fresh, ...$stale], 0, max(0, $count));

        session(['bots.recent' => array_slice([...array_values(array_diff($recent, $chosen)), ...$chosen], -20)]);

        return $chosen;
    }

    /**
     * Sorteia bots distintos (nomes nunca se repetem na mesma sala).
     *
     * @param  list<string>  $exclude  chaves que não podem ser sorteadas
     * @return list<string> chaves dos bots
     */
    public static function pick(int $count, array $exclude = []): array
    {
        $available = array_values(array_diff(array_keys(self::all()), $exclude));
        shuffle($available);

        return array_slice($available, 0, max(0, $count));
    }
}
