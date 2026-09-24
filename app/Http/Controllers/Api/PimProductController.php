<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use App\Services\PimCareProductMapper;
use App\Services\PimInboundAudit;
use App\Services\PimInboundTokenService;
use App\Services\PimPayload;
use App\Services\PimProductDataStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PimProductController extends Controller
{
    public function store(
        Request $request,
        PimPayload $service,
        PimCareProductMapper $mapper,
        PimInboundTokenService $tokens,
    ): JsonResponse {
        $audit = new PimInboundAudit($request, 'product');
        $audit->received();

        try {
            $audit->stage('authentication');
            $this->authorizeInbound($request, $tokens, $audit);

            // PIM asli mengirim payload product secara langsung. Format gabungan
            // { product, image } tetap diterima untuk kompatibilitas simulator lama.
            $audit->stage('payload.validation');
            $isCombined = is_array($request->input('product'));
            $input = [
                'product' => $isCombined ? $request->input('product') : $request->all(),
                'image' => $isCombined && is_array($request->input('image')) ? $request->input('image') : [],
            ];
            $data = $service->validate($input);
            $audit->stage('payload.validated', [
                'generic_sku' => $data['product']['generic'],
                'variant_count' => count($data['product']['variant']),
            ], true);

            if ($request->header('X-Simulate-Atom-Failure') === 'true') {
                $audit->stage('simulation.failure');
                $audit->failureMessage(500, 'Simulated CMS integration failure');

                return response()->json([
                    'status' => false,
                    'message' => 'Simulated CMS integration failure',
                    'request_id' => $audit->requestId(),
                ], 500)->header('X-Request-ID', $audit->requestId());
            }

            return $this->storeProductPayload($data, $service, $mapper, $audit);
        } catch (Throwable $exception) {
            $audit->failure($exception, $this->exceptionStatus($exception));
            throw $exception;
        }
    }

    public function storeImage(
        Request $request,
        PimPayload $service,
        PimInboundTokenService $tokens,
    ): JsonResponse {
        $audit = new PimInboundAudit($request, 'image');
        $audit->received();

        try {
            $audit->stage('authentication');
            $this->authorizeInbound($request, $tokens, $audit);

            // Endpoint ini menerima persis payload "Payload Image to Channel":
            // { generic: [...], variant: [...] }. Wrapper { image: {...} }
            // juga diterima agar klien dapat bermigrasi tanpa breaking change.
            $audit->stage('product.lookup');
            $image = is_array($request->input('image')) ? $request->input('image') : $request->all();
            $product = $this->findProductForImage($image);

            if (! $product || ! is_array($product->pim_payload)) {
                $audit->stage('product.missing');
                $audit->failureMessage(409, 'Payload produk belum tersedia sebelum payload image.');

                return response()->json([
                    'status' => false,
                    'message' => 'Payload produk harus dikirim dan berhasil disimpan sebelum payload image.',
                    'request_id' => $audit->requestId(),
                ], 409)->header('X-Request-ID', $audit->requestId());
            }

            $audit->stage('payload.validation');
            $data = $service->validate(['product' => $product->pim_payload, 'image' => $image]);
            $media = [];
            $audit->stage('media.variant.prepare', ['variant_count' => count($data['product']['variant'])], true);
            foreach ($data['product']['variant'] as $index => $variant) {
                $audit->stage('media.variant.download', [
                    'variant_sku' => $variant['sku'],
                    'variant_position' => $index + 1,
                ]);
                $media[$variant['sku']] = $service->mediaValues($data['product'], $data['image'], $variant['sku'], false);
            }
            $audit->stage('media.generic.download', ['generic_sku' => $data['product']['generic']]);
            $genericMedia = $service->mediaValues($data['product'], $data['image'], $data['product']['generic']);
            $audit->stage('media.completed', [
                'stored_media_count' => count($genericMedia['pim_media']) + collect($media)->sum(fn ($item) => count($item['pim_media'] ?? [])),
            ], true);

            // Unduhan media selesai sebelum transaksi agar URL kadaluwarsa atau
            // file rusak tidak menghasilkan pembaruan produk setengah jalan.
            $audit->stage('database.transaction');
            $updatedVariants = DB::transaction(function () use ($product, $data, $media, $genericMedia, $audit) {
                if (! empty($genericMedia['image'])) {
                    $audit->stage('database.product_image.update');
                    $product->update(['image' => $genericMedia['image']]);
                }

                $updated = 0;
                foreach ($data['product']['variant'] as $pimVariant) {
                    $audit->stage('database.variant_image.update', ['variant_sku' => $pimVariant['sku']]);
                    $variant = $product->variants()->where('sku', $pimVariant['sku'])->first();
                    if (! $variant) {
                        continue;
                    }
                    $image = $media[$variant->sku]['image'] ?? $genericMedia['image'] ?? null;
                    if ($image) {
                        $variant->update(['image' => $image]);
                        $updated++;
                    }
                }

                $audit->stage('database.enrichment.replace');
                app(PimProductDataStore::class)->replace(
                    $product,
                    $data['product'],
                    $data['image'],
                    $genericMedia['pim_media'],
                    source: 'pim-http-image',
                    variantMedia: collect($media)->map(fn ($item) => $item['pim_media'] ?? [])->all(),
                );

                $audit->stage('database.sync_log.create');
                SyncLog::create([
                    'source' => 'pim-image',
                    'status' => 'success',
                    'message' => 'Image PIM artikel '.$data['product']['generic'].' berhasil disinkronkan.',
                    'synced_at' => now(),
                ]);

                return $updated;
            });

            $audit->success(200, [
                'generic_sku' => $data['product']['generic'],
                'variant_images_updated' => $updatedVariants,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payload image PIM berhasil disinkronkan.',
                'request_id' => $audit->requestId(),
                'data' => [
                    'generic' => $data['product']['generic'],
                    'variant_images_updated' => $updatedVariants,
                ],
            ])->header('X-Request-ID', $audit->requestId());
        } catch (Throwable $exception) {
            $audit->failure($exception, $this->exceptionStatus($exception));
            throw $exception;
        }
    }

    private function storeProductPayload(
        array $data,
        PimPayload $service,
        PimCareProductMapper $mapper,
        PimInboundAudit $audit,
    ): JsonResponse {
        // Finish all downloads before the transaction; a failed image never partially updates the catalog.
        $media = [];
        $audit->stage('media.variant.prepare', ['variant_count' => count($data['product']['variant'])], true);
        foreach ($data['product']['variant'] as $index => $variant) {
            $audit->stage('media.variant.download', [
                'variant_sku' => $variant['sku'],
                'variant_position' => $index + 1,
            ]);
            $media[$variant['sku']] = $service->mediaValues($data['product'], $data['image'], $variant['sku'], false);
        }
        $audit->stage('media.generic.download', ['generic_sku' => $data['product']['generic']]);
        $genericMedia = $service->mediaValues($data['product'], $data['image'], $data['product']['generic']);
        $audit->stage('media.completed', [
            'stored_media_count' => count($genericMedia['pim_media']) + collect($media)->sum(fn ($item) => count($item['pim_media'] ?? [])),
        ], true);

        $audit->stage('care.enrichment');
        $catalog = $mapper->map($data['product'], $data['image']);
        $audit->stage('care.completed', [
            'care_found' => $catalog['care_found'],
            'care_variant_count' => count($catalog['variants']),
        ], true);

        $audit->stage('database.transaction');
        $count = DB::transaction(function () use ($data, $media, $genericMedia, $catalog, $audit) {
            $detail = $data['product'];
            $dataStore = app(PimProductDataStore::class);
            foreach ($detail['variant'] as $pimVariant) {
                $audit->stage('database.legacy_product.lookup', ['variant_sku' => $pimVariant['sku']]);
                $existingLegacyProduct = Product::where('sku', $pimVariant['sku'])->first();
                if ($existingLegacyProduct && $existingLegacyProduct->sku !== $detail['generic']) {
                    $audit->stage('database.legacy_product.update', ['variant_sku' => $pimVariant['sku']]);
                    $existingLegacyProduct->update([
                        'name' => $pimVariant['name'],
                        'description' => $catalog['description'],
                        'pim_catalog_active' => true,
                        'image' => $media[$pimVariant['sku']]['image'] ?? $existingLegacyProduct->image,
                    ]);
                    $legacyMedia = array_merge(
                        $media[$pimVariant['sku']]['pim_media'] ?? [],
                        array_values(array_filter(
                            $genericMedia['pim_media'] ?? [],
                            fn ($item) => ! in_array($item['role'] ?? null, ['main_image', 'gallery'], true),
                        )),
                    );
                    $audit->stage('database.legacy_enrichment.replace', ['variant_sku' => $pimVariant['sku']]);
                    $dataStore->replace($existingLegacyProduct, $detail, $data['image'],
                        $legacyMedia, source: 'pim-http');
                }
            }

            $genericSku = $detail['generic'] ?? null;
            if ($genericSku) {
                $parentValues = [
                    'name' => $catalog['name'] ?: ($detail['variant'][0]['name'] ?? 'EIGER Product'),
                    'zone_id' => $catalog['zone_id'],
                    'material' => $catalog['material'],
                    'description' => $catalog['description'],
                    'pim_catalog_active' => true,
                    'image' => $genericMedia['image'] ?? ($detail['mainImage'] ?? null),
                ];
                if ($catalog['care_found']) {
                    $parentValues['price'] = $catalog['price'];
                    $parentValues['stock'] = $catalog['stock'];
                    $parentValues['care_synced_at'] = now();
                }
                $audit->stage('database.parent.upsert', ['generic_sku' => $genericSku]);
                $parent = Product::updateOrCreate(['sku' => $genericSku], $parentValues);

                $syncedSkus = [];
                foreach ($catalog['variants'] as $variant) {
                    $syncedSkus[] = $variant['sku'];
                    $audit->stage('database.variant.upsert', ['variant_sku' => $variant['sku']]);
                    $existingVariant = $parent->variants()->where('sku', $variant['sku'])->first();
                    $savedVariant = $parent->variants()->updateOrCreate(
                        ['sku' => $variant['sku']],
                        [
                            'name' => $variant['name'],
                            'color' => $variant['color'] ?? null,
                            'size' => $variant['size'] ?? null,
                            'ecmsku' => $variant['ecmsku'] ?? null,
                            'moq' => $variant['moq'] ?? null,
                            'price' => $catalog['care_found']
                                ? ($variant['price'] ?? $parent->price)
                                : ($existingVariant?->price ?? 0),
                            'stock' => $catalog['care_found']
                                ? ($variant['stock'] ?? 0)
                                : ($existingVariant?->stock ?? 0),
                            // Prefer the CMS copy. CARE variants often inherit an
                            // article-level PIM URL that is unreachable outside LAN.
                            'image' => $media[$variant['sku']]['image']
                                ?? $genericMedia['image']
                                ?? $variant['image']
                                ?? $parent->image,
                        ]
                    );
                    $audit->stage('database.variant_attributes.replace', ['variant_sku' => $variant['sku']]);
                    $dataStore->replaceVariantAttributes($savedVariant, $variant['customAttributes'] ?? []);
                }
                if ($catalog['care_found']) {
                    $audit->stage('database.variant_prune', ['kept_variant_count' => count($syncedSkus)]);
                    $syncedSkus === []
                        ? $parent->variants()->delete()
                        : $parent->variants()->whereNotIn('sku', $syncedSkus)->delete();
                }

                $audit->stage('database.enrichment.replace', ['generic_sku' => $genericSku]);
                $dataStore->replace(
                    $parent,
                    $detail,
                    $data['image'],
                    $genericMedia['pim_media'],
                    source: 'pim-http',
                    variantMedia: collect($media)->map(fn ($item) => $item['pim_media'] ?? [])->all(),
                );

            }
            $audit->stage('database.sync_log.create');
            SyncLog::create([
                'source' => 'pim', 'status' => 'success',
                'message' => $catalog['care_found']
                    ? 'PIM article '.$detail['generic'].': '.count($catalog['variants']).' varian diperkaya dari CARE.'
                    : 'PIM article '.$detail['generic'].' diterima; CARE belum menyediakan harga/stok sehingga nilai komersial lama dipertahankan.',
                'synced_at' => now(),
            ]);

            return count($catalog['variants']);
        });

        $audit->success(200, [
            'generic_sku' => $data['product']['generic'],
            'synced_variant_count' => $count,
            'care_enriched' => $catalog['care_found'],
        ]);

        return response()->json([
            'status' => true,
            'message' => $catalog['care_found']
                ? 'Produk PIM dan data komersial CARE berhasil disinkronkan.'
                : 'Produk PIM berhasil disimpan; data CARE belum ditemukan dan dapat disinkronkan ulang.',
            'request_id' => $audit->requestId(),
            'data' => ['synced' => $count, 'care_enriched' => $catalog['care_found']],
        ])->header('X-Request-ID', $audit->requestId());
    }

    private function authorizeInbound(
        Request $request,
        PimInboundTokenService $tokens,
        PimInboundAudit $audit,
    ): void {
        if (! config('pim.legacy_http_enabled')) {
            $audit->stage('authentication.endpoint_disabled');
            abort(404);
        }

        if (! $tokens->authenticate($request->bearerToken())) {
            $audit->stage('authentication.token_rejected');
            abort(401);
        }

        $audit->stage('authentication.accepted', [], true);
    }

    private function exceptionStatus(Throwable $exception): int
    {
        if ($exception instanceof ValidationException) {
            return 422;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return 500;
    }

    private function findProductForImage(array $image): ?Product
    {
        $genericSku = trim((string) data_get($image, 'generic.0.sku', ''));
        if ($genericSku !== '') {
            return Product::query()
                ->where('sku', $genericSku)
                ->orWhereHas('pimRecord', fn ($query) => $query->where('generic_sku', $genericSku))
                ->first();
        }

        $variantSku = trim((string) data_get($image, 'variant.0.sku', ''));
        if ($variantSku === '') {
            return null;
        }

        return ProductVariant::query()->where('sku', $variantSku)->first()?->product;
    }
}
