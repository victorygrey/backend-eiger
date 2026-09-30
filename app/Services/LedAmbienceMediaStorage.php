<?php

namespace App\Services;

use App\Support\EigerMediaPath;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class LedAmbienceMediaStorage
{
    /** @var array<string, string> */
    private const EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/aac' => 'aac',
    ];

    public function store(UploadedFile $file, string $type, string $templateKey): string
    {
        $mime = strtolower((string) $file->getMimeType());
        $extension = self::EXTENSIONS[$mime] ?? null;
        if (! $extension || ! str_starts_with($mime, $type.'/')) {
            throw new RuntimeException("Format media {$type} tidak didukung.");
        }

        $hash = hash_file('sha256', $file->getRealPath());
        if (! $hash) {
            throw new RuntimeException('Checksum media LED Ambience tidak dapat dibuat.');
        }

        $folder = 'led-ambience/'.($type === 'video' ? 'videos' : 'audio').'/'.$templateKey;
        $root = rtrim((string) config('led_ambience.media_path'), DIRECTORY_SEPARATOR);
        $directory = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $folder);
        $target = $directory.DIRECTORY_SEPARATOR.$hash.'.'.$extension;

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Folder media LED Ambience tidak dapat dibuat.');
        }

        if (! is_file($target) && ! copy($file->getRealPath(), $target)) {
            throw new RuntimeException('Media LED Ambience gagal disimpan ke storage NAS.');
        }

        return EigerMediaPath::publicUrl($folder.'/'.$hash.'.'.$extension);
    }
}
