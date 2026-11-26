<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Foto e capa de perfil só podem ser imagens PARADAS. GIF já fica de fora pelo tipo (mimes), mas WebP e PNG
 * também podem ser animados (WebP animado e APNG): isso pesaria no banco e foge do padrão do perfil.
 * Animação de fundo só nos fundos prontos do jogo (config/profiles.php).
 */
class StaticImage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $head = (string) @file_get_contents($value->getRealPath(), false, null, 0, 4096);

        $gif = str_starts_with($head, 'GIF8');
        // WebP: "RIFF....WEBP" + bloco VP8X com o bit de animação, ou um bloco ANIM.
        $animatedWebp = str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP'
            && (str_contains($head, 'ANIM') || (substr($head, 12, 4) === 'VP8X' && (ord($head[20] ?? "\0") & 0x02)));
        // APNG: PNG com o bloco acTL antes da imagem.
        $apng = str_starts_with($head, "\x89PNG") && str_contains($head, 'acTL');

        if ($gif || $animatedWebp || $apng) {
            $fail('Use uma imagem parada (JPG, PNG ou WebP). GIFs e imagens animadas não são aceitos na foto nem na capa.');
        }
    }
}
