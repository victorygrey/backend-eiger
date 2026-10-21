<?php

namespace App\Console\Commands;

use App\Services\AtomProductImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ImportAtomSelectionCommand extends Command
{
    protected $signature = 'atom:import-selection {manifest : JSON daftar SKU dan slug yang sudah diverifikasi}
        {--dry-run : Validasi manifest tanpa menulis database atau media}
        {--skip-media : Simpan URL media tanpa mengunduh}
        {--limit=0 : Batasi jumlah artikel yang diproses}';

    protected $description = 'Tambahkan artikel pilihan ATOM tanpa mengubah produk CMS yang sudah ada';

    public function handle(AtomProductImportService $importer): int
    {
        $path = (string) $this->argument('manifest');
        if (! is_file($path)) {
            $this->error('Manifest tidak ditemukan.');

            return self::FAILURE;
        }
        try {
            $manifest = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
            Validator::make($manifest, [
                'products' => ['required', 'array', 'min:1'],
                'products.*.sku' => ['required', 'string', 'regex:/^[0-9]{9}$/', 'distinct'],
                'products.*.slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
                'products.*.name' => ['required', 'string', 'max:255'],
                'products.*.gender' => ['required', 'string'],
                'products.*.gender_source' => ['required', 'string'],
                'products.*.product_type' => ['required', 'string'],
                'products.*.activity' => ['present', 'string'],
                'products.*.resources' => ['sometimes', 'array:detail,variants,variant_images'],
                'products.*.resources.detail' => ['required_with:products.*.resources', 'array'],
                'products.*.resources.variants' => ['required_with:products.*.resources', 'array', 'min:1'],
                'products.*.resources.variant_images' => ['required_with:products.*.resources', 'array'],
                'protected_skus' => ['sometimes', 'array'],
                'protected_skus.*' => ['string', 'regex:/^[0-9]{9}$/'],
            ])->validate();
            foreach ($manifest['products'] as $row) {
                if (isset($row['resources']) && (string) ($row['resources']['detail']['skuProduct'] ?? '') !== $row['sku']) {
                    throw new \RuntimeException('SKU resource berbeda dari manifest: '.$row['sku']);
                }
                foreach ($row['resources']['variants'] ?? [] as $variant) {
                    if (! preg_match('/^'.preg_quote($row['sku'], '/').'[0-9]{3}$/', (string) ($variant['sku'] ?? ''))) {
                        throw new \RuntimeException('SKU varian berbeda dari artikel: '.$row['sku']);
                    }
                }
            }
        } catch (Throwable $exception) {
            $this->error('Manifest tidak valid: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info(count($manifest['products']).' artikel; semua produk existing akan dilewati.');
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        $failed = 0;
        $processed = 0;
        $limit = max(0, (int) $this->option('limit'));
        foreach ($manifest['products'] as &$row) {
            if (in_array($row['status'] ?? '', ['imported', 'skipped_existing', 'protected'], true)) {
                continue;
            }
            if ($limit && $processed >= $limit) {
                break;
            }
            try {
                if (in_array($row['sku'], $manifest['protected_skus'] ?? [], true)) {
                    $row['status'] = 'protected';
                } else {
                    $row['result'] = $importer->import($row, ! $this->option('skip-media'), true, $row['resources'] ?? null);
                    $row['status'] = $row['result']['status'];
                }
                unset($row['error']);
                $this->info($row['sku'].' '.$row['status']);
            } catch (Throwable $exception) {
                $row['status'] = 'failed';
                $row['error'] = $exception->getMessage();
                $failed++;
                $this->error($row['sku'].' failed: '.$exception->getMessage());
            }
            $row['processed_at'] = now()->toIso8601String();
            $processed++;
            File::put($path.'.tmp', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            File::move($path.'.tmp', $path);
        }
        unset($row);
        $this->table(['Status', 'Jumlah'], collect($manifest['products'])->countBy(fn ($row) => $row['status'] ?? 'pending')
            ->map(fn ($count, $status) => [$status, $count])->values()->all());

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
