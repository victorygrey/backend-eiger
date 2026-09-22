<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class PimMediaController extends Controller
{
    public function show(string $path)
    {
        abort_unless(preg_match('#^(?:[a-z0-9][a-z0-9-]*/)*[a-f0-9]{64}\.(png|jpg|jpeg|webp|mp4|webm)$#D', $path), 404);

        $relative = str_replace('/', DIRECTORY_SEPARATOR, $path);
        $candidate = rtrim((string) config('pim.media_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relative;
        if (! is_file($candidate) && ! str_contains($path, '/')) {
            $candidate = rtrim((string) config('pim.legacy_media_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relative;
        }
        abort_unless(is_file($candidate) && ! is_link($candidate), 404);

        return response()->file($candidate, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
