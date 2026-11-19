<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Illuminate\Support\Str;

class ProfileImageOptimizer
{
    public function store(UploadedFile $file, string $directory, int $maxDimension): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecreatetruecolor')) {
            $path = $file->storePublicly($directory, 'public');
            if ($path === false) {
                throw new RuntimeException('Não foi possível armazenar a imagem de perfil.');
            }
            return $path;
        }

        $image = @imagecreatefromstring($file->get());

        if ($image === false) {
            throw new RuntimeException('Não foi possível decodificar a imagem enviada.');
        }

        $optimized = null;

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(1, $maxDimension / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $optimized = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($optimized === false) {
                throw new RuntimeException('Não foi possível preparar a imagem otimizada.');
            }

            if (function_exists('imagewebp')) {
                imagealphablending($optimized, false);
                imagesavealpha($optimized, true);
                $transparent = imagecolorallocatealpha($optimized, 0, 0, 0, 127);
                imagefilledrectangle($optimized, 0, 0, $targetWidth, $targetHeight, $transparent);
            } else {
                $background = imagecolorallocate($optimized, 255, 255, 255);
                imagefilledrectangle($optimized, 0, 0, $targetWidth, $targetHeight, $background);
            }

            if (! imagecopyresampled(
                $optimized,
                $image,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $width,
                $height
            )) {
                throw new RuntimeException('Não foi possível redimensionar a imagem enviada.');
            }

            ob_start();
            $encoded = function_exists('imagewebp')
                ? imagewebp($optimized, null, 82)
                : imagejpeg($optimized, null, 84);
            $contents = ob_get_clean();

            if (! $encoded || $contents === false || $contents === '') {
                throw new RuntimeException('Não foi possível codificar a imagem otimizada.');
            }

            $extension = function_exists('imagewebp') ? 'webp' : 'jpg';
            $path = "{$directory}/".Str::uuid().".{$extension}";

            if (! Storage::disk('public')->put($path, $contents)) {
                throw new RuntimeException('Não foi possível armazenar a imagem otimizada.');
            }

            return $path;
        } finally {
            imagedestroy($image);

            if ($optimized !== null && $optimized !== false) {
                imagedestroy($optimized);
            }
        }
    }
}
