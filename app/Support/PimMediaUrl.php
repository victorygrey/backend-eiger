<?php

namespace App\Support;

class PimMediaUrl
{
    public static function toPublicUrl(?string $mediaUrl): ?string
    {
        if ($mediaUrl === null || $mediaUrl === '') {
            return $mediaUrl;
        }

        if (str_starts_with($mediaUrl, '/api/pim-media/')) {
            return url($mediaUrl);
        }

        $source = parse_url($mediaUrl);
        $pimBase = parse_url((string) config('pim.url'));
        $path = $source['path'] ?? '';
        $filename = basename($path);

        $samePimHost = $source !== false
            && $pimBase !== false
            && strtolower((string) ($source['host'] ?? '')) === strtolower((string) ($pimBase['host'] ?? ''))
            && (int) ($source['port'] ?? self::defaultPort($source['scheme'] ?? null))
                === (int) ($pimBase['port'] ?? self::defaultPort($pimBase['scheme'] ?? null));

        if ($samePimHost && preg_match('/^\/media\/([a-f0-9]{64})\.(jpg|jpeg|png|webp|mp4|webm)$/i', $path)) {
            foreach (['media_path', 'legacy_media_path'] as $key) {
                if (is_file(rtrim((string) config('pim.'.$key), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename)) {
                    return url('/api/pim-media/'.$filename);
                }
            }
        }

        return $mediaUrl;
    }

    private static function defaultPort(?string $scheme): int
    {
        return strtolower((string) $scheme) === 'https' ? 443 : 80;
    }
}
