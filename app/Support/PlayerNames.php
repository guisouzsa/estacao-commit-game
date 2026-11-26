<?php

namespace App\Support;

use App\Models\GuestProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regras de nome compartilhadas por contas, visitantes e bots.
 *
 * Dois nomes são o mesmo nome quando suas "chaves" coincidem: minúsculas, sem
 * acentos e com espaços normalizados. " Guilherme " e "GUILHERME" colidem.
 */
class PlayerNames
{
    /** Letras aceitas em nomes: alfabeto latino, com os acentos do português (e de línguas latinas). */
    private const LATIN = 'A-Za-zÀ-ÖØ-öø-ÿ';

    /**
     * Remove espaços sobrando e normaliza o texto para exibição. Um "@" no começo é tirado: o @ já aparece
     * antes de todo nome no jogo, então "@ana" vira "ana".
     */
    public static function display(string $raw): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $raw));

        return ltrim((string) preg_replace('/^@+\s*/u', '', $name));
    }

    /**
     * Formato de nome de VISITANTE. Só alfabeto latino (letras de outros alfabetos e emojis não), números,
     * espaço, ponto, hífen e sublinhado; começa com letra; símbolos nunca seguidos nem no fim; nenhum
     * caractere repetido mais de 3 vezes seguidas.
     */
    public static function formatProblem(string $name): ?string
    {
        $letter = self::LATIN;
        if (! preg_match("/^[{$letter}][{$letter}0-9]*(?:[ ._-][{$letter}0-9]+)*$/u", $name)) {
            if (preg_match("/[^{$letter}0-9 ._-]/u", $name)) {
                return 'Use só letras do nosso alfabeto (com ou sem acento), números, espaço, ponto, hífen ou sublinhado.';
            }
            if (! preg_match("/^[{$letter}]/u", $name)) {
                return 'O nome deve começar com uma letra.';
            }

            return 'Não use símbolos seguidos nem no fim do nome.';
        }

        return self::repeatProblem($name);
    }

    /**
     * Formato do USERNAME de uma conta (vai na URL do perfil): letras sem acento, números, "_" e "-";
     * começa com letra; símbolos nunca seguidos nem no fim; nada repetido mais de 3 vezes seguidas.
     */
    public static function usernameProblem(string $username): ?string
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*(?:[_-][A-Za-z0-9]+)*$/', $username)) {
            if (preg_match('/[^A-Za-z0-9_-]/', $username)) {
                return 'Use só letras sem acento, números, _ ou -.';
            }
            if (! preg_match('/^[A-Za-z]/', $username)) {
                return 'O nome de usuário deve começar com uma letra.';
            }

            return 'Não use _ ou - seguidos nem no fim do nome.';
        }

        return self::repeatProblem($username);
    }

    private static function repeatProblem(string $name): ?string
    {
        return preg_match('/(.)\1{3,}/iu', $name) ? 'Não repita o mesmo caractere mais de 3 vezes seguidas.' : null;
    }

    /** Chave de comparação (e de unicidade) do nome. */
    public static function key(string $raw): string
    {
        return Str::lower(Str::ascii(self::display($raw)));
    }

    /**
     * Valida um nome para um NOVO visitante ou conta. Retorna a mensagem de erro
     * (amigável) ou null se o nome estiver livre.
     */
    public function problem(string $raw, ?string $ignoreUserId = null, ?string $ignoreGuestId = null): ?string
    {
        $name = self::display($raw);
        $min = (int) config('players.name.min');
        $max = (int) config('players.name.max');
        $length = mb_strlen($name);

        if ($length < $min || $length > $max) {
            return "O nome deve ter entre {$min} e {$max} caracteres.";
        }

        if ($format = self::formatProblem($name)) {
            return $format;
        }

        $key = self::key($name);

        if ($this->isReserved($key) || $this->isBlocked($key)) {
            return 'Esse nome não pode ser usado. Escolha outro.';
        }

        if ($this->isTaken($key, $ignoreUserId, $ignoreGuestId)) {
            return 'Esse nome já está sendo utilizado.';
        }

        return null;
    }

    /**
     * Alternativas livres para um nome ocupado. Nunca troca o nome sozinha: a pessoa escolhe.
     *
     * @return list<string>
     */
    public function suggest(string $raw, int $limit = 3): array
    {
        $max = (int) config('players.name.max');
        $base = mb_substr(preg_replace('/[^\p{L}\p{N}]+$/u', '', self::display($raw)), 0, max(1, $max - 3));
        $suffixes = ['_dev', (string) random_int(10, 99), '_git', (string) random_int(100, 999), '_commit', '_'.random_int(1, 9)];
        $found = [];

        foreach ($suffixes as $suffix) {
            $candidate = mb_substr($base, 0, $max - mb_strlen($suffix)).$suffix;
            if ($this->problem($candidate) === null && ! in_array($candidate, $found, true)) {
                $found[] = $candidate;
            }
            if (count($found) >= $limit) {
                break;
            }
        }

        return $found;
    }

    /** Nome usado por uma conta permanente ou por um visitante ativo. */
    public function isTaken(string $key, ?string $ignoreUserId = null, ?string $ignoreGuestId = null): bool
    {
        return $this->takenByUser($key, $ignoreUserId) || $this->takenByGuest($key, $ignoreGuestId);
    }

    public function takenByUser(string $key, ?string $ignoreUserId = null): bool
    {
        return User::query()
            ->whereRaw('LOWER(username) = ?', [$key])
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->exists();
    }

    /** Visitante ATIVO: perfis expirados e já liberáveis não seguram o nome. */
    public function takenByGuest(string $key, ?string $ignoreGuestId = null): bool
    {
        return GuestProfile::query()
            ->where('name_key', $key)
            ->when($ignoreGuestId, fn ($query) => $query->where('id', '!=', $ignoreGuestId))
            ->where(fn ($query) => $query
                ->where('expires_at', '>', now())
                // Expirado, mas preso a uma partida em andamento: o nome continua dele.
                ->orWhereNotIn('id', GuestProfile::query()->withoutActiveMatch()->select('id')))
            ->exists();
    }

    public function isReserved(string $key): bool
    {
        $compact = str_replace([' ', '_', '-', '.'], '', $key);

        foreach (config('players.reserved') as $reserved) {
            if ($compact === str_replace(' ', '', Str::lower(Str::ascii($reserved)))) {
                return true;
            }
        }

        foreach (config('bots.catalog') as $bot) {
            if ($compact === str_replace([' ', '_', '-', '.'], '', self::key($bot['name']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nome ofensivo? Testa várias leituras do mesmo nome, para pegar os disfarces comuns:
     * - com e sem a troca de números/símbolos por letras ("m3rda", "$hit", "f0da");
     * - tudo junto, sem separadores ("b.u.c.e.t.a", "p u t a");
     * - com letras repetidas reduzidas a uma ("buuuceta", "merrrda").
     * Termos de `blocked_contains` valem em qualquer posição; os de `blocked_words`, só como palavra
     * inteira ou como o nome inteiro (para não barrar "computador", "cuidado" ou "therapist").
     */
    public function isBlocked(string $key): bool
    {
        $leet = strtr($key, [
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '8' => 'b', '9' => 'g',
            '@' => 'a', '$' => 's', '!' => 'i', '|' => 'i', '+' => 't',
        ]);

        $readings = [];
        foreach ([$key, $leet] as $text) {
            $words = preg_split('/[^a-z]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $compact = implode('', $words);
            $readings[] = [$compact, $words];
            $readings[] = [self::collapse($compact), array_map([self::class, 'collapse'], $words)];
        }

        foreach ($readings as [$compact, $words]) {
            foreach (config('players.blocked_contains') as $term) {
                if (str_contains($compact, $term) || str_contains($compact, self::collapse($term))) {
                    return true;
                }
            }
            foreach (config('players.blocked_words') as $term) {
                foreach ([$term, self::collapse($term)] as $form) {
                    if ($compact === $form || in_array($form, $words, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** "buuuceta" -> "buceta": letras repetidas viram uma só. */
    private static function collapse(string $text): string
    {
        return (string) preg_replace('/(.)\1+/', '$1', $text);
    }

    /**
     * Serializa a criação de nomes iguais entre tabelas diferentes (users e
     * guest_profiles não podem ter um índice único em comum). No PostgreSQL usa
     * um lock consultivo da transação; em outros bancos não faz nada.
     */
    public function lock(string $key): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['player-name:'.$key]);
        }
    }
}
