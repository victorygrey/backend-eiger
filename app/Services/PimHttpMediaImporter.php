<?php

namespace App\Services;

use App\Support\EigerMediaPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PimHttpMediaImporter
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    public function import(string $url, string $role, array $context = []): array
    {
        $base = rtrim((string) config('pim.url'), '/');
        $parts = parse_url($url);
        $expectedHash = null;
        $expectedExtension = null;
        if (preg_match('~^'.preg_quote($base, '~').'/media/([a-f0-9]{64})\.(jpg|jpeg|png|webp|mp4|webm)$~Di', $url, $matches)) {
            $expectedHash = strtolower($matches[1]);
            $expectedExtension = strtolower($matches[2]) === 'jpeg' ? 'jpg' : strtolower($matches[2]);
        } elseif (! $parts || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || ! in_array(strtolower($parts['host'] ?? ''), config('pim.media_hosts', []), true)) {
            throw ValidationException::withMessages(['media' => 'Sumber media harus berasal dari PIM atau host media yang diizinkan.']);
        }

        $root = rtrim((string) config('pim.media_path'), DIRECTORY_SEPARATOR);
        $this->ensureDirectory($root);
        if ($expectedHash && $expectedExtension) {
            $expectedMime = array_search($expectedExtension, self::MIME_EXTENSIONS, true);
            if ($expectedMime !== false) {
                $relative = EigerMediaPath::directory($expectedMime, $role, $context).'/'.$expectedHash.'.'.$expectedExtension;
                $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (is_file($target) && ! is_link($target) && hash_file('sha256', $target) === $expectedHash) {
                    $this->makeReadable($target);

                    return $this->result($target, $relative, $expectedHash, $role);
                }
            }
        }

        $temp = tempnam($root, '.download-');
        if ($temp === false) {
            throw new \RuntimeException('Cannot create temporary media file');
        }

        try {
            $sourceExtension = strtolower(pathinfo((string) ($parts['path'] ?? ''), PATHINFO_EXTENSION));
            $expectsVideo = in_array($sourceExtension, ['mp4', 'webm'], true)
                || str_contains(strtolower($role), 'video');
            $maximum = $expectsVideo
                ? (int) config('pim.max_video_bytes')
                : (int) config('pim.max_image_bytes');
            $response = Http::connectTimeout((int) config('pim.media_connect_timeout', 10))
                ->timeout(120)
                ->retry(
                    max(1, (int) config('pim.media_download_attempts', 4)),
                    fn (int $attempt) => min(250 * (2 ** ($attempt - 1)), 2000),
                    fn (?\Throwable $exception) => $exception instanceof ConnectionException,
                    false,
                )->withOptions([
                    'allow_redirects' => false,
                    'sink' => $temp,
                    'progress' => function ($total, $received) use ($maximum) {
                        if ($total > $maximum || $received > $maximum) {
                            throw new \RuntimeException('PIM media exceeds the configured size limit');
                        }
                    },
                ])->get($url);
            if (filesize($temp) === 0 && $response->body() !== '') {
                $body = $response->body();
                if (strlen($body) > $maximum) {
                    throw new \RuntimeException('PIM media exceeds the configured size limit');
                }
                file_put_contents($temp, $body);
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temp);
            $extension = self::MIME_EXTENSIONS[$mime] ?? null;
            $limit = str_starts_with((string) $mime, 'video/')
                ? (int) config('pim.max_video_bytes')
                : (int) config('pim.max_image_bytes');
            $hash = hash_file('sha256', $temp);
            if ($response->status() !== 200 || ! $extension || filesize($temp) > $limit
                || ($expectedHash && ($hash !== $expectedHash || $extension !== $expectedExtension))) {
                throw ValidationException::withMessages(['media' => 'Media PIM tidak tersedia, kedaluwarsa, terlalu besar, atau isi/checksum tidak valid.']);
            }

            $relativeDirectory = EigerMediaPath::directory($mime, $role, $context);
            $directory = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
            $this->ensureDirectory($directory);
            $relative = $relativeDirectory.'/'.$hash.'.'.$extension;
            $target = $directory.DIRECTORY_SEPARATOR.$hash.'.'.$extension;
            if (is_link($target)) {
                throw new \RuntimeException('PIM media symlinks are not supported');
            }
            if (is_file($target) && hash_file('sha256', $target) !== $hash) {
                throw ValidationException::withMessages(['media' => 'Salinan lokal media PIM rusak. Pulihkan file sebelum mengimpor ulang.']);
            }
            if (! is_file($target) && ! rename($temp, $target)) {
                throw new \RuntimeException('Cannot store PIM media');
            }
            $this->makeReadable($target);

            return $this->result($target, $relative, $hash, $role);
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    private function result(string $path, string $relative, string $hash, string $role): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return [
            'url' => EigerMediaPath::publicUrl($relative),
            'role' => $role,
            'sha256' => $hash,
            'mime' => $mime,
            'type' => str_starts_with($mime, 'video/') ? 'video' : 'image',
        ];
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new \RuntimeException('Cannot create EIGER media directory');
        }
    }

    private function makeReadable(string $path): void
    {
        // TrueNAS ACL datasets can reject chmod even though the mounted file is
        // already readable by the www-data user that serves /api/pim-media.
        if (! @chmod($path, 0644) && ! is_readable($path)) {
            throw new \RuntimeException('Cannot make PIM media readable');
        }
    }
}
