<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\PimHttpMediaImporter;
use App\Support\EigerMediaPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OrganizeEigerMediaCommand extends Command
{
    protected $signature = 'media:organize {--download-remote : Download approved remote PIM media} {--remove-legacy : Remove legacy files after all database references are migrated}';

    protected $description = 'Organize PIM media into product photo, product video, and LED Ambience video folders';

    public function handle(PimHttpMediaImporter $importer): int
    {
        $migrated = 0;
        $downloaded = 0;
        $failed = 0;
        $legacyFiles = [];

        ProductMedia::with('product')->orderBy('id')->chunkById(100, function ($rows) use (
            $importer, &$migrated, &$downloaded, &$failed, &$legacyFiles
        ) {
            foreach ($rows as $media) {
                $product = $media->product;
                if (! $product) {
                    continue;
                }
                $context = ['product_name' => $product->name, 'generic_sku' => $product->sku];

                try {
                    if (str_starts_with($media->url, '/api/pim-media/')) {
                        $relative = substr($media->url, strlen('/api/pim-media/'));
                        if (str_contains($relative, '/')) {
                            continue;
                        }
                        $source = $this->legacyFile($relative);
                        if (! $source) {
                            $failed++;
                            $this->warn('File lama tidak ditemukan: '.$relative);

                            continue;
                        }
                        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
                        $directory = EigerMediaPath::directory($mime, (string) $media->role, $context);
                        $destination = $this->copyToRoot($source, $directory, $relative);
                        $newUrl = EigerMediaPath::publicUrl($directory.'/'.$relative);
                        $this->updateReferences($media, $newUrl, $mime);
                        $legacyFiles[$source] = true;
                        $migrated++;

                        continue;
                    }

                    if ($this->option('download-remote') && preg_match('#^https://#i', $media->url)) {
                        $stored = $importer->import($media->url, (string) $media->role, $context);
                        $this->updateReferences($media, $stored['url'], $stored['mime'], $stored['sha256']);
                        $downloaded++;
                    }
                } catch (\Throwable $error) {
                    $failed++;
                    $this->warn('Media #'.$media->id.' gagal: '.$error->getMessage());
                }
            }
        });

        Product::with('variants')->orderBy('id')->chunkById(100, function ($products) use (&$migrated, &$failed, &$legacyFiles) {
            foreach ($products as $product) {
                try {
                    if ($newUrl = $this->organizeStandalone($product, $product->image)) {
                        $legacyFiles[$this->legacyFile(basename($product->image))] = true;
                        $product->updateQuietly(['image' => $newUrl]);
                        $migrated++;
                    }
                    foreach ($product->variants as $variant) {
                        if ($newUrl = $this->organizeStandalone($product, $variant->image)) {
                            $legacyFiles[$this->legacyFile(basename($variant->image))] = true;
                            $variant->update(['image' => $newUrl]);
                            $migrated++;
                        }
                    }
                } catch (\Throwable $error) {
                    $failed++;
                    $this->warn('Referensi produk '.$product->sku.' gagal: '.$error->getMessage());
                }
            }
        });

        if ($this->option('remove-legacy')) {
            foreach (array_keys($legacyFiles) as $file) {
                if ($this->mayRemove($file)) {
                    @unlink($file);
                }
            }
        }

        $this->info("Selesai: $migrated file lama dipindahkan, $downloaded media remote diunduh, $failed gagal.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function legacyFile(string $filename): ?string
    {
        if (! preg_match('/^[a-f0-9]{64}\.(png|jpg|jpeg|webp|mp4|webm)$/D', $filename)) {
            return null;
        }
        foreach (['legacy_media_path', 'media_path'] as $key) {
            $candidate = rtrim((string) config('pim.'.$key), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
            if (is_file($candidate) && ! is_link($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function copyToRoot(string $source, string $directory, string $filename): string
    {
        $root = rtrim((string) config('pim.media_path'), DIRECTORY_SEPARATOR);
        $targetDirectory = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);
        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0755, true) && ! is_dir($targetDirectory)) {
            throw new \RuntimeException('Folder tujuan tidak dapat dibuat.');
        }
        $destination = $targetDirectory.DIRECTORY_SEPARATOR.$filename;
        if (! is_file($destination) && ! copy($source, $destination)) {
            throw new \RuntimeException('File media tidak dapat disalin.');
        }
        if (hash_file('sha256', $source) !== hash_file('sha256', $destination)) {
            throw new \RuntimeException('Checksum hasil salinan tidak cocok.');
        }
        @chmod($destination, 0644);

        return $destination;
    }

    private function organizeStandalone(Product $product, ?string $url): ?string
    {
        if (! is_string($url) || ! preg_match('#^/api/pim-media/([^/]+)$#D', $url, $matches)) {
            return null;
        }
        $source = $this->legacyFile($matches[1]);
        if (! $source) {
            throw new \RuntimeException('File lama tidak ditemukan: '.$matches[1]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        $directory = EigerMediaPath::directory($mime, 'main_image', [
            'product_name' => $product->name,
            'generic_sku' => $product->sku,
        ]);
        $this->copyToRoot($source, $directory, $matches[1]);

        return EigerMediaPath::publicUrl($directory.'/'.$matches[1]);
    }

    private function updateReferences(ProductMedia $media, string $newUrl, string $mime, ?string $checksum = null): void
    {
        $oldUrl = $media->url;
        DB::transaction(function () use ($media, $oldUrl, $newUrl, $mime, $checksum) {
            $media->update([
                'url' => $newUrl,
                'mime_type' => $mime,
                'media_type' => str_starts_with($mime, 'video/') ? 'video' : 'image',
                'checksum' => $checksum ?? $media->checksum,
                'source_url' => $media->source_url ?: (preg_match('#^https?://#i', $oldUrl) ? $oldUrl : null),
            ]);
            Product::whereKey($media->product_id)->where('image', $oldUrl)->update(['image' => $newUrl]);
            DB::table('product_variants')->where('product_id', $media->product_id)->where('image', $oldUrl)->update(['image' => $newUrl]);
            DB::table('product_technologies')->where('product_id', $media->product_id)->where('image_url', $oldUrl)->update(['image_url' => $newUrl]);
        });
    }

    private function mayRemove(string $file): bool
    {
        $url = '/api/pim-media/'.basename($file);

        return ! ProductMedia::where('url', $url)->exists()
            && ! Product::where('image', $url)->exists()
            && ! DB::table('product_variants')->where('image', $url)->exists()
            && ! DB::table('product_technologies')->where('image_url', $url)->exists();
    }
}
