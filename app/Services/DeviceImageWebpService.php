<?php

namespace App\Services;

use App\Support\PimMediaUrl;
use Symfony\Component\Process\Process;

/**
 * Creates compact, local WebP copies only for the in-store device contract.
 *
 * PIM originals are intentionally retained unchanged. A deterministic output
 * path lets the media endpoint cache the derivative permanently and prevents a
 * photo from being re-encoded on every device request.
 */
class DeviceImageWebpService
{
    public function url(?string $mediaUrl): ?string
    {
        $publicUrl = PimMediaUrl::toPublicUrl($mediaUrl);
        if (! $publicUrl) {
            return $publicUrl;
        }

        $relative = $this->localRelativePath($publicUrl);
        if (! $relative) {
            return $publicUrl;
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if ($extension === 'webp') {
            return $publicUrl;
        }

        $source = $this->sourcePath($relative);
        if (! $source || is_link($source)) {
            return $publicUrl;
        }

        $imageInfo = @getimagesize($source);
        $mime = strtolower((string) ($imageInfo['mime'] ?? ''));
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return $publicUrl;
        }

        $sourceHash = @hash_file('sha256', $source);
        if (! $sourceHash) {
            return $publicUrl;
        }

        $targetRelative = $this->targetRelativePath($relative, $sourceHash);
        $target = rtrim((string) config('pim.media_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $targetRelative);

        if (! is_file($target) && ! $this->encode($source, $imageInfo, $target)) {
            return $publicUrl;
        }

        return url('/api/pim-media/'.$targetRelative);
    }

    private function localRelativePath(string $url): ?string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $prefix = '/api/pim-media/';
        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        $relative = ltrim(substr($path, strlen($prefix)), '/');

        return preg_match('#^(?:[a-z0-9][a-z0-9-]*/)*[a-f0-9]{64}\.(?:jpg|jpeg|png|webp)$#Di', $relative)
            ? $relative
            : null;
    }

    private function sourcePath(string $relative): ?string
    {
        $root = rtrim((string) config('pim.media_path'), DIRECTORY_SEPARATOR);
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($path)) {
            return $path;
        }

        if (str_contains($relative, '/')) {
            return null;
        }

        $legacy = rtrim((string) config('pim.legacy_media_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$relative;

        return is_file($legacy) ? $legacy : null;
    }

    private function targetRelativePath(string $sourceRelative, string $sourceHash): string
    {
        $directory = dirname($sourceRelative);
        $fingerprint = hash('sha256', implode('|', [
            $sourceRelative,
            $sourceHash,
            max(1, min(100, (int) config('pim.device_image_webp_quality', 80))),
            max(1, (int) config('pim.device_image_max_edge', 1600)),
        ]));

        return ($directory === '.' ? '' : $directory.'/').'device-webp/'.$fingerprint.'.webp';
    }

    /** @param array<int|string, mixed> $imageInfo */
    private function encode(string $sourcePath, array $imageInfo, string $targetPath): bool
    {
        try {
            $sourceWidth = (int) ($imageInfo[0] ?? 0);
            $sourceHeight = (int) ($imageInfo[1] ?? 0);
            if ($sourceWidth < 1 || $sourceHeight < 1) {
                return false;
            }

            $edge = max(1, (int) config('pim.device_image_max_edge', 1600));
            $scale = min(1, $edge / max($sourceWidth, $sourceHeight));
            $width = max(1, (int) round($sourceWidth * $scale));
            $height = max(1, (int) round($sourceHeight * $scale));
            $directory = dirname($targetPath);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                return false;
            }

            $temporary = $targetPath.'.tmp-'.bin2hex(random_bytes(6));
            $process = new Process([
                'cwebp',
                '-quiet',
                '-q', (string) max(1, min(100, (int) config('pim.device_image_webp_quality', 80))),
                '-resize', (string) $width, (string) $height,
                $sourcePath,
                '-o', $temporary,
            ]);
            $process->setTimeout(120);
            $process->run();
            if (! $process->isSuccessful() || ! is_file($temporary)) {
                @unlink($temporary);

                return false;
            }

            if (! @rename($temporary, $targetPath)) {
                @unlink($temporary);
                if (! is_file($targetPath) || is_link($targetPath)) {
                    return false;
                }
            }
            @chmod($targetPath, 0644);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
