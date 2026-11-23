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
    /** Remove espaços sobrando e normaliza o texto para exibição. */
    public static function display(string $raw): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $raw));
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

        if (! preg_match('/^[\p{L}\p{N}][\p{L}\p{N}_\-. ]*[\p{L}\p{N}]$/u', $name)) {
            return 'Use apenas letras, números, espaço, ponto, hífen ou sublinhado.';
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

    public function isBlocked(string $key): bool
    {
        // Troca de caracteres usados para driblar filtros (leetspeak).
        $plain = strtr($key, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);
        $compact = preg_replace('/[^a-z]/', '', $plain);

        foreach (config('players.blocked_contains') as $term) {
            if (str_contains($compact, $term)) {
                return true;
            }
        }

        $words = preg_split('/[^a-z]+/', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach (config('players.blocked_words') as $term) {
            if (in_array($term, $words, true) || $compact === $term) {
                return true;
            }
        }

        return false;
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
