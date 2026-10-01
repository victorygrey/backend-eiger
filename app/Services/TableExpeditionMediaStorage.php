<?php

namespace App\Services;

use App\Support\EigerMediaPath;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class TableExpeditionMediaStorage
{
    /** @var array<string, array{type:string,extension:string}> */
    private const FORMATS = [
        'image/jpeg' => ['type' => 'image', 'extension' => 'jpg'],
        'image/png' => ['type' => 'image', 'extension' => 'png'],
        'image/webp' => ['type' => 'image', 'extension' => 'webp'],
        'video/mp4' => ['type' => 'video', 'extension' => 'mp4'],
        'video/webm' => ['type' => 'video', 'extension' => 'webm'],
    ];

    /** @return array{type:string,url:string} */
    public function store(UploadedFile $file): array
    {
        $mime = strtolower((string) $file->getMimeType());
        $format = self::FORMATS[$mime] ?? null;
        if (! $format) {
            throw new RuntimeException('Format media standby tidak didukung.');
        }

        $hash = hash_file('sha256', $file->getRealPath());
        if (! $hash) {
            throw new RuntimeException('Checksum media standby tidak dapat dibuat.');
        }

        $folder = 'table-expedition/standby/'.$format['type'];
        $root = rtrim((string) config('table_expedition.media_path'), DIRECTORY_SEPARATOR);
        $directory = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $folder);
        $target = $directory.DIRECTORY_SEPARATOR.$hash.'.'.$format['extension'];

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Folder media Table Expedition tidak dapat dibuat.');
        }

        if (! is_file($target) && ! copy($file->getRealPath(), $target)) {
            throw new RuntimeException('Media standby gagal disimpan ke storage NAS.');
        }

        return [
            'type' => $format['type'],
            'url' => EigerMediaPath::publicUrl($folder.'/'.$hash.'.'.$format['extension']),
        ];
    }

    public function delete(?string $url): void
    {
        if (! is_string($url) || ! preg_match(
            '#^/api/pim-media/(table-expedition/standby/(?:image|video)/[a-f0-9]{64}\.(?:jpg|png|webp|mp4|webm))$#i',
            $url,
            $matches,
        )) {
            return;
        }

        $root = rtrim((string) config('table_expedition.media_path'), DIRECTORY_SEPARATOR);
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $matches[1]);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
