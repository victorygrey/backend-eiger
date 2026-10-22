<?php

namespace App\Services;

use App\Models\PhotoCapture;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FitPhotoStorage
{
    public function thumbnail(UploadedFile $file, string $path): ?string
    {
        $dimensions = getimagesize($file->getRealPath());
        // Bound decoder memory. Very large images still retain the full original.
        if (! $dimensions || $dimensions[0] * $dimensions[1] > 16000000) {
            return null;
        }
        $source = match ($file->getMimeType()) {
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
        };
        if (! $source) {
            return null;
        }
        $width = max(1, (int) round($dimensions[0] * min(270 / $dimensions[1], 180 / $dimensions[0], 1)));
        $height = max(1, (int) round($dimensions[1] * $width / $dimensions[0]));
        $thumbnail = imagecreatetruecolor($width, $height);
        imagefill($thumbnail, 0, 0, imagecolorallocate($thumbnail, 255, 255, 255));
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $dimensions[0], $dimensions[1]);
        ob_start();
        imagejpeg($thumbnail, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($thumbnail);
        imagedestroy($source);
        Storage::disk('fit-photos')->put($path, $bytes);

        return $path;
    }

    public function response(PhotoCapture $capture, bool $thumbnail = false): StreamedResponse
    {
        abort_unless($capture->hasAvailablePhoto(), 410, 'Photo expired or unavailable.');
        abort_unless($capture->storage_disk === 'fit-photos', 404);
        $path = $thumbnail ? $capture->thumbnail_path : $capture->photo_path;
        abort_unless($path && Storage::disk('fit-photos')->exists($path), 404);

        $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$capture->image_mime];

        return Storage::disk('fit-photos')->response($path, $capture->capture_code.($thumbnail ? '-preview.jpg' : '.'.$extension), [
            'Content-Type' => $thumbnail ? 'image/jpeg' : $capture->image_mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
