<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PimHttpMediaImporter
{
    public function import(string $url, string $role): array
    {
        $base = rtrim(config('pim.url'), '/');
        $parts = parse_url($url);
        $expectedHash = null;
        $expectedExtension = null;
        if (preg_match('~^'.preg_quote($base, '~').'/media/([a-f0-9]{64})\.(jpg|png|webp)$~D', $url, $matches)) {
            $expectedHash = $matches[1];
            $expectedExtension = $matches[2];
        } elseif (!$parts || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || !in_array(strtolower($parts['host'] ?? ''), config('pim.media_hosts', []), true)) {
            throw ValidationException::withMessages(['image' => 'Sumber gambar harus berasal dari PIM atau host media yang diizinkan.']);
        }
        $dir = config('pim.media_path');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new \RuntimeException('Cannot create PIM media directory');
        if ($expectedHash) {
            $name = $expectedHash.'.'.$expectedExtension;
            $target = $dir.DIRECTORY_SEPARATOR.$name;
            if (is_file($target) && !is_link($target) && hash_file('sha256', $target) === $expectedHash) {
                return $this->result($target, $name, $expectedHash, $role);
            }
        }
        $temp = tempnam($dir, '.download-');
        try {
            $response = Http::connectTimeout(5)->timeout(30)->withOptions([
                'allow_redirects' => false,
                'sink' => $temp,
                'progress' => function ($total, $received) {
                    if ($total > 10485760 || $received > 10485760) throw new \RuntimeException('PIM image exceeds 10 MiB');
                },
            ])->get($url);
            // Some HTTP adapters return the body without writing the sink.
            // Preserve the same size limit before using that body.
            if (filesize($temp) === 0 && $response->body() !== '') {
                $body = $response->body();
                if (strlen($body) > 10485760) throw new \RuntimeException('PIM image exceeds 10 MiB');
                file_put_contents($temp, $body);
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temp);
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
            $hash = hash_file('sha256', $temp);
            if ($response->status() !== 200 || !$extension || filesize($temp) > 10485760
                || ($expectedHash && ($hash !== $expectedHash || $extension !== $expectedExtension))) {
                // Do not expose signed URL credentials in an upstream exception message.
                throw ValidationException::withMessages(['image' => 'Gambar PIM tidak tersedia, kedaluwarsa, atau isi/checksum tidak valid.']);
            }
            $name = $hash.'.'.$extension;
            $target = $dir.DIRECTORY_SEPARATOR.$name;
            if (is_link($target)) throw new \RuntimeException('PIM media symlinks are not supported');
            if (is_file($target) && hash_file('sha256', $target) !== $hash) {
                throw ValidationException::withMessages(['image' => 'Salinan lokal gambar PIM rusak. Pulihkan file media sebelum mengimpor ulang.']);
            }
            if (!is_file($target) && !rename($temp, $target)) throw new \RuntimeException('Cannot store PIM media');
            return $this->result($target, $name, $hash, $role);
        } finally {
            if (is_file($temp)) unlink($temp);
        }
    }

    private function result(string $path, string $name, string $hash, string $role): array
    {
        return ['url' => '/api/pim-media/'.$name, 'role' => $role, 'sha256' => $hash,
            'mime' => (new \finfo(FILEINFO_MIME_TYPE))->file($path)];
    }
}
