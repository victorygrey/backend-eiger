<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RfidTag;
use App\Models\TableExpeditionConfig;
use App\Models\TableExpeditionItem;
use App\Services\TableExpeditionComparisonService;
use App\Services\TableExpeditionReadiness;
use App\Support\DeviceProductPayload;
use App\Support\PimMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableExpeditionApiController extends Controller
{
    public function standby(): JsonResponse
    {
        $mediaUrl = TableExpeditionConfig::get('standby_media_url');

        return response()->json([
            'status' => 'success',
            'screen' => 'standby',
            'data' => [
                'title' => TableExpeditionConfig::get('standby_title', 'EIGER Table Expedition Hub'),
                'subtitle' => TableExpeditionConfig::get('standby_subtitle', 'Letakkan produk ber-tag RFID di atas meja untuk melihat detail produk.'),
                'instructions' => $this->instructions(),
                'media' => $mediaUrl ? [
                    'type' => TableExpeditionConfig::get('standby_media_type'),
                    'url' => PimMediaUrl::toPublicUrl($mediaUrl),
                ] : null,
            ],
        ]);
    }

    public function scan(
        Request $request,
        TableExpeditionComparisonService $comparisons,
        TableExpeditionReadiness $readiness,
    ): JsonResponse {
        $validated = $request->validate([
            'rfid_tag' => ['required_without:rfid', 'nullable', 'string', 'max:64'],
            'rfid' => ['required_without:rfid_tag', 'nullable', 'string', 'max:64'],
        ]);
        $rfidTag = RfidTag::canonicalUid((string) ($validated['rfid_tag'] ?? $validated['rfid']));

        $item = $this->activeItemByRfid($rfidTag);
        if (! $item || ! $item->product) {
            return response()->json([
                'status' => 'not_found',
                'matched' => false,
                'message' => "Tag RFID '{$rfidTag}' tidak aktif pada modul Table Expedition.",
                'rfid_tag' => $rfidTag,
            ], 404);
        }

        $product = $item->product;
        $comparisonProducts = $comparisons->for($item);
        $item->update(['last_scanned_at' => now()]);
        RfidTag::recordScan($rfidTag);

        return response()->json([
            'status' => 'success',
            'matched' => true,
            'screen' => 'product_detail',
            'source' => 'master_rfid',
            'data' => [
                'rfid_tag' => $rfidTag,
                'readiness' => $readiness->inspect($product),
                'product' => DeviceProductPayload::make($product),
                'comparison_products' => $comparisonProducts
                    ->map(fn (Product $comparison): array => DeviceProductPayload::make($comparison))
                    ->values(),
            ],
        ]);
    }

    public function itemLost(Request $request): JsonResponse
    {
        $rfid = $request->input('rfid_tag') ?? $request->input('rfid');

        return response()->json([
            'status' => 'success',
            'event' => 'item_lost',
            'screen' => 'standby',
            'data' => [
                'action' => 'reset_to_standby',
                'previous_rfid' => is_string($rfid) ? RfidTag::canonicalUid($rfid) : null,
            ],
        ]);
    }

    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id_1' => ['nullable', 'integer'],
            'product_id_2' => ['nullable', 'integer'],
            'rfid_primary' => ['nullable', 'string', 'max:64'],
            'rfid_secondary' => ['nullable', 'string', 'max:64'],
        ]);

        $primaryItem = $this->resolveActiveItem(
            $validated['product_id_1'] ?? null,
            $validated['rfid_primary'] ?? null,
        );
        $secondaryItem = $this->resolveActiveItem(
            $validated['product_id_2'] ?? null,
            $validated['rfid_secondary'] ?? null,
        );

        if (! $primaryItem?->product || ! $secondaryItem?->product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kedua produk harus aktif pada Master RFID untuk Table Expedition.',
            ], 422);
        }

        $primary = $primaryItem->product;
        $secondary = $secondaryItem->product;

        if ($primary->is($secondary)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pilih dua produk yang berbeda untuk komparasi.',
            ], 422);
        }

        $configuredComparisonIds = collect($primaryItem->similar_product_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id);
        if (! $configuredComparisonIds->contains((int) $secondary->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk kedua tidak terdaftar sebagai pilihan komparasi untuk produk utama.',
            ], 422);
        }

        $primaryPayload = DeviceProductPayload::make($primary);
        $secondaryPayload = DeviceProductPayload::make($secondary);

        return response()->json([
            'status' => 'success',
            'data' => [
                'primary' => [
                    'rfid_tag' => isset($validated['rfid_primary'])
                        ? RfidTag::canonicalUid($validated['rfid_primary'])
                        : null,
                    'product' => $primaryPayload,
                ],
                'secondary' => [
                    'rfid_tag' => isset($validated['rfid_secondary'])
                        ? RfidTag::canonicalUid($validated['rfid_secondary'])
                        : null,
                    'product' => $secondaryPayload,
                ],
                'comparison' => [
                    'price_difference' => abs((float) $primary->price - (float) $secondary->price),
                    'stock_difference' => abs((int) $primary->stock - (int) $secondary->stock),
                    'shared_activities' => collect($primaryPayload['activities'])
                        ->pluck('name')
                        ->intersect(collect($secondaryPayload['activities'])->pluck('name'))
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }

    public function status(TableExpeditionReadiness $readiness): JsonResponse
    {
        $items = TableExpeditionItem::query()
            ->where('is_active', true)
            ->with([
                'product.variants',
                'product.technologiesRelation',
                'product.specificationsRelation',
                'product.customAttributesRelation',
                'product.mediaRelation',
            ])
            ->get();
        $ready = $items->filter(fn (TableExpeditionItem $item): bool => $readiness->inspect($item->product)['ready'])->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'mode' => 'standby',
                'active_items' => $items->count(),
                'ready_items' => $ready,
                'incomplete_items' => $items->count() - $ready,
                'standby_media_configured' => filled(TableExpeditionConfig::get('standby_media_url')),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    private function activeItemByRfid(string $rfidTag): ?TableExpeditionItem
    {
        return TableExpeditionItem::query()
            ->where(function ($query) use ($rfidTag): void {
                $query->where('rfid_tag', $rfidTag)
                    ->orWhereRaw(
                        "UPPER(REPLACE(REPLACE(REPLACE(rfid_tag, '-', ''), ':', ''), ' ', '')) = ?",
                        [$rfidTag],
                    );
            })
            ->where('is_active', true)
            ->with(array_map(
                fn (string $relation): string => 'product.'.$relation,
                DeviceProductPayload::relations(),
            ))
            ->first();
    }

    private function resolveActiveItem(mixed $productId, mixed $rfid): ?TableExpeditionItem
    {
        if (! $productId && ! is_string($rfid)) {
            return null;
        }

        $item = TableExpeditionItem::query()
            ->where('is_active', true)
            ->when($productId, fn ($query) => $query->where('product_id', $productId))
            ->when(! $productId && is_string($rfid), function ($query) use ($rfid): void {
                $canonical = RfidTag::canonicalUid($rfid);
                $query->where(function ($query) use ($canonical): void {
                    $query->where('rfid_tag', $canonical)
                        ->orWhereRaw(
                            "UPPER(REPLACE(REPLACE(REPLACE(rfid_tag, '-', ''), ':', ''), ' ', '')) = ?",
                            [$canonical],
                        );
                });
            })
            ->with(array_map(
                fn (string $relation): string => 'product.'.$relation,
                DeviceProductPayload::relations(),
            ))
            ->first();

        return $item;
    }

    /** @return list<string> */
    private function instructions(): array
    {
        $instructions = TableExpeditionConfig::get('usage_instructions', []);

        return is_array($instructions) ? array_values($instructions) : [];
    }
}
