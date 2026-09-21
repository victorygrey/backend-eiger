<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PimFolderImporter
{
    public function scan(bool $retryFailed = false): array
    {
        $root = realpath(config('pim.folder'));
        if ($root === false || !is_dir($root) || !is_readable($root)) {
            throw new RuntimeException('Folder PIM tidak dapat dibaca. Periksa PIM_FOLDER dan akses SMB/mount.');
        }
        // All manual/scheduled scans use the same OS lock, released even if PHP exits.
        $lock = fopen(storage_path('framework/pim-import.lock'), 'c');
        if (!$lock) throw new RuntimeException('Tidak dapat membuka lock scanner.');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('Pemindaian PIM lain masih berjalan.');
        }
        $result = ['imported' => 0, 'failed' => 0, 'skipped' => 0];
        try {
            $entries = scandir($root);
            if ($entries === false) throw new RuntimeException('Tidak dapat membaca isi folder PIM.');
            $processed = 0;
            foreach ($entries as $entry) {
                if (!preg_match('/^([A-Za-z0-9_-]{1,100})\.ready$/D', $entry, $match)) continue;
                $batch = $match[1];
                $directory = $root.DIRECTORY_SEPARATOR.$entry;
                if (!is_dir($directory) || is_link($directory)) continue;
                $record = DB::table('pim_imports')->where('batch_id', $batch)->first();
                // Ready batches are immutable. A correction must have a new batch ID.
                if ($record && $record->status === 'imported') {
                    $result['skipped']++;
                    continue;
                }
                $checksum = '';
                $attempted = false;
                try {
                    $manifestPath = $this->safeFile($directory, 'manifest.json');
                    if (filesize($manifestPath) > 2 * 1024 * 1024) throw new RuntimeException('Manifest melebihi 2 MiB.');
                    $raw = file_get_contents($manifestPath);
                    if ($raw === false) throw new RuntimeException('Gagal membaca manifest.');
                    $checksum = hash('sha256', $raw);
                    if ($record && $record->checksum === $checksum && !$retryFailed) {
                        $result['skipped']++;
                        continue;
                    }
                    if ($processed >= 100) break;
                    $processed++;
                    $attempted = true;
                    $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    $this->validateManifest($manifest, $batch);
                    $prepared = [];
                    foreach ($manifest['products'] as $product) {
                        $media = [];
                        foreach ($product['media'] as $asset) {
                            $file = $this->safeFile($directory, $asset['file']);
                            $media[] = $this->copyMedia($file, $asset);
                        }
                        $prepared[] = [
                            'sku' => $product['sku'], 'name' => $product['name'],
                            'description' => $product['description'] ?? '',
                            'image' => collect($media)->firstWhere('role', 'main_image')['url'],
                            'pim_media' => $media,
                        ];
                    }
                    $version = $manifest['created_at'].'|'.$batch;
                    DB::transaction(function () use ($prepared, $version, $batch, $checksum, $record) {
                        $updated = 0;
                        foreach ($prepared as $values) {
                            $product = Product::firstOrNew(['sku' => $values['sku']]);
                            // Old drops arriving late must never overwrite more recent content.
                            if ($product->pim_version && strcmp($product->pim_version, $version) >= 0) continue;
                            $media = $values['pim_media'];
                            unset($values['pim_media']);
                            $product->fill($values);
                            $product->save();
                            $payload = $product->pim_payload ?? [
                                'generic' => $product->sku,
                                'name' => $product->name,
                                'mainImage' => $product->image,
                                'variant' => [],
                                'customAtributes' => [],
                                'media' => [],
                                'technology' => [],
                                'activity' => [],
                                'specification' => [],
                            ];
                            app(PimProductDataStore::class)->replace(
                                $product, $payload, $product->pim_image_payload ?? [], $media, $version, 'pim-folder'
                            );
                            $updated++;
                        }
                        $message = "$updated SKU diperbarui dari batch $batch.";
                        $this->record($batch, $checksum, 'imported', $message, $record);
                        SyncLog::create(['source' => 'pim-folder', 'status' => 'success', 'message' => $message, 'synced_at' => now()]);
                    });
                    $result['imported']++;
                } catch (\Throwable $error) {
                    // Do not flood logs for an unchanged failed batch on every scheduled scan.
                    if ($record && $record->status === 'failed' && $record->checksum === $checksum && !$retryFailed) {
                        $result['skipped']++;
                        continue;
                    }
                    if (!$attempted) {
                        if ($processed >= 100) break;
                        $processed++;
                    }
                    $message = mb_substr($error->getMessage(), 0, 2000);
                    $this->record($batch, $checksum, 'failed', $message, $record);
                    SyncLog::create(['source' => 'pim-folder', 'status' => 'failed', 'message' => "$batch: $message", 'synced_at' => now()]);
                    $result['failed']++;
                }
            }
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function validateManifest(array $manifest, string $batch): void
    {
        Validator::make($manifest, [
            'schema_version' => 'required|integer|in:1',
            'batch_id' => 'required|string|in:'.$batch,
            'created_at' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/D'],
            'article_generic' => 'required|string|max:255',
            'products' => 'required|array|min:1|max:1000',
            'products.*.sku' => 'required|string|max:255|distinct',
            'products.*.name' => 'required|string|max:255',
            'products.*.description' => 'nullable|string|max:100000',
            'products.*.media' => 'required|array|min:1|max:30',
            'products.*.media.*.file' => 'required|string|max:255',
            'products.*.media.*.sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'products.*.media.*.role' => 'required|in:main_image,image,video',
        ])->validate();
        foreach ($manifest['products'] as $product) {
            if (collect($product['media'])->where('role', 'main_image')->count() !== 1) {
                throw new RuntimeException('Setiap SKU harus memiliki tepat satu main_image.');
            }
        }
    }

    private function safeFile(string $directory, string $relative): string
    {
        if (!preg_match('#^[A-Za-z0-9_/-]+\.[A-Za-z0-9]+$#D', $relative) || str_contains($relative, '..')) {
            throw new RuntimeException('Path file tidak valid.');
        }
        $candidate = $directory;
        foreach (explode('/', $relative) as $part) {
            $candidate .= DIRECTORY_SEPARATOR.$part;
            if (is_link($candidate)) throw new RuntimeException('Symlink tidak diizinkan dalam paket.');
        }
        $file = realpath($candidate);
        $parent = realpath($directory).DIRECTORY_SEPARATOR;
        if (!$file || !str_starts_with($file, $parent) || !is_file($file) || !is_readable($file)) {
            throw new RuntimeException('File paket tidak tersedia: '.$relative);
        }
        return $file;
    }

    private function copyMedia(string $file, array $asset): array
    {
        if (filesize($file) > 100 * 1024 * 1024) throw new RuntimeException('Media melebihi 100 MiB.');
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'mp4' => 'video/mp4', 'webm' => 'video/webm'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!isset($allowed[$ext]) || $mime !== $allowed[$ext]) throw new RuntimeException('Tipe media tidak didukung atau tidak cocok.');
        if (($asset['role'] === 'video') !== str_starts_with($mime, 'video/')) throw new RuntimeException('Role media tidak cocok dengan tipe file.');
        $targetRoot = config('pim.media_path');
        if (!is_dir($targetRoot) && !mkdir($targetRoot, 0755, true) && !is_dir($targetRoot)) throw new RuntimeException('Tidak dapat membuat folder media CMS.');
        $name = $asset['sha256'].'.'.$ext;
        $destination = $targetRoot.DIRECTORY_SEPARATOR.$name;
        $temporary = tempnam($targetRoot, 'import-');
        if ($temporary === false) throw new RuntimeException('Tidak dapat menyiapkan media CMS.');
        try {
            if (!copy($file, $temporary) || hash_file('sha256', $temporary) !== $asset['sha256']) {
                throw new RuntimeException('Checksum media tidak cocok; paket belum lengkap atau rusak.');
            }
            if (!is_file($destination)) {
                if (!rename($temporary, $destination)) throw new RuntimeException('Gagal menyimpan media CMS.');
            } elseif (hash_file('sha256', $destination) !== $asset['sha256']) {
                throw new RuntimeException('Media CMS yang tersimpan rusak.');
            }
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
        if (!@chmod($destination, 0644)) {
            throw new RuntimeException('Tidak dapat mengatur izin baca media CMS.');
        }
        return ['url' => '/api/pim-media/'.$name, 'role' => $asset['role'], 'sha256' => $asset['sha256'], 'mime' => $mime];
    }

    private function record(string $batch, string $checksum, string $status, string $message, ?object $previous): void
    {
        DB::table('pim_imports')->updateOrInsert(['batch_id' => $batch], [
            'checksum' => $checksum, 'status' => $status, 'message' => $message,
            'attempts' => ($previous->attempts ?? 0) + 1,
            'created_at' => $previous->created_at ?? now(), 'updated_at' => now(),
        ]);
    }
}
