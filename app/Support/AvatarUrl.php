<?php

namespace App\Support;

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
