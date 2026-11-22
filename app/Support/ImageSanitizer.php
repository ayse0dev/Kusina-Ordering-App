<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageSanitizer
{
    private const MAX_DIMENSION = 1200;

    public static function store(UploadedFile $file, string $dir = 'menu', int $maxDimension = self::MAX_DIMENSION): string
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['image' => 'Only JPG, PNG or WebP images are allowed.']);
        }

        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            throw ValidationException::withMessages(['image' => 'That image could not be read.']);
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, $maxDimension / max($w, $h));
        $dst = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);

        ob_start();
        imagewebp($dst, null, 82);
        $bytes = ob_get_clean();

        $path = $dir.'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }
}
