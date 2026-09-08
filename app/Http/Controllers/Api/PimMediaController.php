<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class PimMediaController extends Controller
{
    public function show(string $filename)
    {
        abort_unless(preg_match('/^[a-f0-9]{64}\.(png|jpg|jpeg|webp|mp4|webm)$/D', $filename), 404);
        $path = config('pim.media_path').DIRECTORY_SEPARATOR.$filename;
        abort_unless(is_file($path) && !is_link($path), 404);
        return response()->file($path, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
