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
