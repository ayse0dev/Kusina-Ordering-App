<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PhotoUrl
{
    public static function resolve(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (! Storage::disk('public')->exists($path) && is_file(public_path('images/' . $path))) {
            return asset('images/' . $path);
        }

        return asset('storage/' . $path);
    }
}
