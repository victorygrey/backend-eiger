<?php

namespace App\Console\Commands;

use App\Services\AtomCatalogClient;
use App\Services\AtomProductImportService;
use App\Services\AtomProductSelector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportAtomProductsCommand extends Command
{
    protected $signature = 'atom:import-products
        {--per-type=50 : Jumlah produk per kombinasi gender dan jenis}
        {--manifest= : Path manifest/checkpoint; default di NAS eiger-media/imports}
        {--fresh : Buat ulang pilihan produk meskipun manifest sudah ada}
        {--dry-run : Pilih produk dan simpan manifest tanpa mengubah database}
        {--skip-media : Simpan URL ATOM tanpa mengunduh foto ke NAS}
        {--limit=0 : Batasi jumlah item yang diproses untuk pengujian}';

    protected $description = 'Import katalog sementara dari EIGER ATOM ke skema produk PIM CMS';

    public function handle(
        AtomCatalogClient $atom,
        AtomProductSelector $selector,
        AtomProductImportService $importer,
    ): int {
        $perType = max(1, (int) $this->option('per-type'));
        $path = (string) ($this->option('manifest') ?: $this->defaultManifestPath());
        $manifest = ! $this->option('fresh') ? $this->readManifest($path) : null;

        if (! is_array($manifest) || (int) ($manifest['per_type'] ?? 0) !== $perType) {
            $this->info('Mengambil katalog ATOM dan menyusun kuota produk...');
            $products = $selector->select($atom->catalog(), $perType);
            $manifest = [
                'schema_version' => 1,
                'generated_at' => now()->toIso8601String(),
                'per_type' => $perType,
                'products' => array_map(fn ($product) => $product + [
                    'status' => 'pending',
                    'attempts' => 0,
                ], $products),
            ];
            $this->writeManifest($path, $manifest);
        }

        $this->renderSummary($manifest['products']);
        $this->line('Manifest: '.$path);
        if ($this->option('dry-run')) {
            $this->info('Dry run selesai; database dan media tidak diubah.');

            return self::SUCCESS;
        }

        $limit = max(0, (int) $this->option('limit'));
        $processed = 0;
        $failed = 0;
        foreach ($manifest['products'] as $index => &$product) {
            if (($product['status'] ?? null) === 'done') {
                continue;
            }
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $product['attempts'] = (int) ($product['attempts'] ?? 0) + 1;
            $this->line(sprintf(
                '[%d/%d] %s %s — %s (%s)',
                $index + 1,
                count($manifest['products']),
                strtoupper((string) $product['gender']),
                strtoupper((string) $product['product_type']),
                $product['name'],
                $product['sku'],
            ));

            try {
                $product['result'] = $importer->import($product, ! $this->option('skip-media'));
                $product['status'] = 'done';
                $product['imported_at'] = now()->toIso8601String();
                unset($product['error']);
                $this->info('  selesai');
            } catch (Throwable $exception) {
                $failed++;
                $product['status'] = 'failed';
                $product['error'] = $exception->getMessage();
                $this->error('  gagal: '.$exception->getMessage());
            }
            $processed++;
            $manifest['updated_at'] = now()->toIso8601String();
            $this->writeManifest($path, $manifest);
        }
        unset($product);

        $done = collect($manifest['products'])->where('status', 'done')->count();
        $pending = count($manifest['products']) - $done;
        $this->newLine();
        $this->info("Selesai: {$done}; tersisa/gagal: {$pending}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function readManifest(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function defaultManifestPath(): string
    {
        return rtrim((string) config('pim.media_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'imports'
            .DIRECTORY_SEPARATOR.'atom-products-manifest.json';
    }

    private function writeManifest(string $path, array $manifest): void
    {
        File::ensureDirectoryExists(dirname($path));
        $temp = $path.'.tmp';
        file_put_contents($temp, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        rename($temp, $path);
    }

    private function renderSummary(array $products): void
    {
        $rows = collect($products)->groupBy(fn ($item) => $item['gender'].'.'.$item['product_type'])
            ->map(function ($items, $key) {
                [$gender, $type] = explode('.', $key, 2);

                return [
                    strtoupper($gender),
                    strtoupper($type),
                    $items->count(),
                    $items->where('gender_source', 'explicit')->count(),
                    $items->where('gender_source', 'neutral_fallback')->count(),
                    $items->pluck('activity_group')->unique()->sort()->implode(', '),
                ];
            })->values()->all();
        $this->table(
            ['Gender', 'Jenis', 'Jumlah', 'Gender eksplisit', 'Netral', 'Kelompok aktivitas'],
            $rows,
        );
    }
}
