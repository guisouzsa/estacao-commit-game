<?php

namespace App\Support;

use App\Models\GuestProfile;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cria e reconhece perfis temporários de visitante.
 *
 * A identidade vem SEMPRE do cookie HttpOnly (token aleatório de 256 bits;
 * só o hash vai para o banco). Nada que o navegador envie no corpo da requisição
 * é aceito como prova de quem a pessoa é.
 */
class GuestIdentity
{
    private const ATTRIBUTE = 'visitor.profile';

    /** Só reescreve a atividade se passou este intervalo (poupa o banco). */
    private const TOUCH_EVERY_MINUTES = 5;

    public function __construct(private PlayerNames $names) {}

    /**
     * @return array{profile: GuestProfile, token: string}
     *
     * @throws ValidationException
     */
    public function create(string $rawName, string $style, string $seed): array
    {
        $display = PlayerNames::display($rawName);
        $key = PlayerNames::key($display);

        try {
            return DB::transaction(function () use ($display, $key, $style, $seed): array {
                // Serializa quem disputa o mesmo nome, inclusive contra o cadastro de contas.
                $this->names->lock($key);

                // Perfis vencidos (e sem partida em andamento) liberam o nome.
                GuestProfile::query()->where('name_key', $key)->expired()->deletable()->delete();

                if ($problem = $this->names->problem($display)) {
                    throw ValidationException::withMessages(['name' => $problem]);
                }

                $token = bin2hex(random_bytes(32));

                $profile = GuestProfile::create([
                    'display_name' => $display,
                    'name_key' => $key,
                    'avatar_style' => $style,
                    'avatar_seed' => $seed,
                    'token_hash' => hash('sha256', $token),
                    'last_seen_at' => now(),
                    'expires_at' => $this->newExpiry(),
                ]);

                return ['profile' => $profile, 'token' => $token];
            });
        } catch (QueryException $exception) {
            // Duas pessoas escolhendo o mesmo nome ao mesmo tempo: o índice único decide.
            if (str_contains(strtolower($exception->getMessage()), 'name_key')) {
                throw ValidationException::withMessages(['name' => 'Esse nome já está sendo utilizado.']);
            }

            throw $exception;
        }
    }

    /** Perfil dono do cookie da requisição, ou null (token ausente, inválido ou expirado). */
    public function resolve(Request $request): ?GuestProfile
    {
        if ($request->attributes->has(self::ATTRIBUTE)) {
            return $request->attributes->get(self::ATTRIBUTE);
        }

        $profile = $this->lookup($request);
        $request->attributes->set(self::ATTRIBUTE, $profile);

        return $profile;
    }

    public function cookie(Request $request, string $token): Cookie
    {
        return cookie(
            config('players.guest_cookie'),
            $token,
            60 * 24 * 7,
            '/',
            null,
            $request->isSecure() || (bool) config('session.secure'),
            true,   // HttpOnly: o JavaScript nunca lê o token
            false,
            'lax',
        );
    }

    public function forgetCookie(): Cookie
    {
        return cookie()->forget(config('players.guest_cookie'));
    }

    private function lookup(Request $request): ?GuestProfile
    {
        $token = $request->cookie(config('players.guest_cookie'));

        if (! is_string($token) || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $profile = GuestProfile::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        if ($profile && $profile->last_seen_at->lt(now()->subMinutes(self::TOUCH_EVERY_MINUTES))) {
            // Janela deslizante, mas com TETO absoluto: ninguém mantém um perfil temporário (e o nome dele) vivo para sempre
            // só mandando requisições. Passou do teto, o perfil vence no horário já gravado.
            $ceiling = $profile->created_at->copy()->addDays(max(1, (int) config('players.guest_max_days')));
            $profile->forceFill(['last_seen_at' => now(), 'expires_at' => $this->newExpiry()->min($ceiling)])->save();
        }

        return $profile;
    }

    private function newExpiry(): \Illuminate\Support\Carbon
    {
        return now()->addHours((int) config('players.guest_ttl_hours'));
    }
}
