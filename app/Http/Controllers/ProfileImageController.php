<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

/**
 * Entrega a foto e a capa de um perfil como IMAGEM de verdade.
 *
 * As imagens ficam no banco como data URI (o disco do Render Free é efêmero). Mandar o data URI
 * dentro do JSON de cada página custava centenas de KB por visita e não cacheava nada. Agora o
 * JSON leva só uma URL curta com a versão (`?v=`), e o navegador guarda a imagem por um ano:
 * ela só é baixada de novo quando o perfil muda (a versão muda junto).
 *
 * Só formatos raster: SVG é recusado (poderia carregar script).
 */
class ProfileImageController extends Controller
{
    private const MIMES = ['png' => 'image/png', 'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'];

    public function __invoke(string $kind, User $user): Response
    {
        abort_unless(in_array($kind, ['avatar', 'cover'], true), 404);

        $raw = (string) ($kind === 'avatar' ? $user->avatar : $user->profile_cover);

        if (! preg_match('#^data:image/(png|jpe?g|webp|gif);base64,([A-Za-z0-9+/=\s]+)$#', $raw, $match)) {
            abort(404);
        }

        $binary = base64_decode($match[2], true);
        abort_if($binary === false || $binary === '', 404);

        return response($binary, 200, [
            'Content-Type' => self::MIMES[$match[1]],
            'Content-Length' => (string) strlen($binary),
            // A URL leva a versão: pode ser guardada "para sempre".
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
