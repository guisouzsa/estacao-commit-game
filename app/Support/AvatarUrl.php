<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Único ponto que transforma o caminho de uma imagem de perfil em URL.
 *
 * Usa um caminho RELATIVO (/storage/...), então não depende de APP_URL estar
 * correta no Render. URLs absolutas externas (ex.: login com Google) passam
 * como estão.
 */
class AvatarUrl
{
    /**
     * Foto/capa de um USUÁRIO. Imagem guardada como data URI vira uma URL curta e versionada
     * (ver ProfileImageController): o JSON das páginas não carrega mais a imagem inteira.
     */
    public static function forUser(User $user, string $kind = 'avatar'): ?string
    {
        $raw = $kind === 'cover' ? $user->profile_cover : $user->avatar;

        if (is_string($raw) && Str::startsWith($raw, 'data:image/')) {
            return route('profile.image', ['kind' => $kind, 'user' => $user->id, 'v' => $user->updated_at?->timestamp ?? 0], false);
        }

        return self::for($raw);
    }

    public static function for(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // "gen:estilo:semente" é um ícone gerado, não uma imagem: não há URL.
        if (Str::startsWith($path, 'gen:')) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:image/'])) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
