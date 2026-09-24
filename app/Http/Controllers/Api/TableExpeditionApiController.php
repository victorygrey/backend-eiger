<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RfidTag;
use App\Models\TableExpeditionConfig;
use App\Models\TableExpeditionItem;
use App\Support\DeviceProductPayload;
use App\Support\PimMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableExpeditionApiController extends Controller
{
    /**
     * Get standby screen data and usage instructions.
     * SRS FR-TABLE-01 & FR-TABLE-07: Idle screen saat tidak ada item terdeteksi beserta panduan wahana.
     * GET /api/v1/table-expedition/standby
     */
    public function standby(): JsonResponse
    {
        $title = TableExpeditionConfig::get('standby_title', 'EIGER Table Expedition Hub');
        $subtitle = TableExpeditionConfig::get('standby_subtitle', 'Letakkan produk ber-tag RFID di atas meja untuk melihat spesifikasi detail dan komparasi.');
        $rawInstructions = TableExpeditionConfig::get('usage_instructions', []);
        $instructions = is_array($rawInstructions) ? $rawInstructions : (json_decode($rawInstructions, true) ?: []);

        return response()->json([
            'status' => 'success',
            'screen' => 'standby',
            'data' => [
                'title' => $title,
                'subtitle' => $subtitle,
                'instructions' => $instructions,
            ],
        ]);
    }

    /**
     * Scan RFID tag placed on the Table Expedition reader.
     * SRS FR-TABLE-02 to FR-TABLE-06: Menampilkan Home screen produk, ukuran/warna, spesifikasi detail,
     * video, AI Summary, dan rekomendasi produk serupa.
     * POST /api/v1/table-expedition/scan
     */
    public function scan(Request $request): JsonResponse
    {
        $tagInput = $request->input('rfid_tag') ?? $request->input('rfid');
        if (! $tagInput) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tag RFID wajib diisi (parameter rfid_tag atau rfid).',
            ], 422);
        }

        $rfidTag = trim(strtoupper($tagInput));

        // 1. Check custom Table Expedition mapping
        $item = TableExpeditionItem::with(array_map(
            fn (string $relation): string => 'product.'.$relation,
            DeviceProductPayload::relations()
        ))
            ->where('rfid_tag', $rfidTag)
            ->where('is_active', true)
            ->first();

        $product = null;
        $idealFor = null;
        $videoUrl = null;
        $aiSummary = null;
        $similarProducts = collect();

        if ($item && $item->product) {
            $product = $item->product;
            $idealFor = $item->ideal_for;
            $aiSummary = $item->ai_summary;
            $similarProducts = $item->similar_products;

            $item->update(['last_scanned_at' => now()]);
        } else {
            // Fallback to general rfid_tags
            $generalTag = RfidTag::with(array_map(
                fn (string $relation): string => 'product.'.$relation,
                DeviceProductPayload::relations()
            ))
                ->where('uid', $rfidTag)
                ->first();

            if ($generalTag && $generalTag->product) {
                $product = $generalTag->product;
            }
        }

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'matched' => false,
                'message' => "Tag RFID '{$rfidTag}' tidak terdaftar pada modul Table Expedition.",
                'rfid_tag' => $rfidTag,
            ], 404);
        }

        foreach ($product->pim_media ?? [] as $media) {
            $url = is_string($media) ? $media : ($media['url'] ?? $media['value'] ?? null);
            if (is_string($url) && (preg_match('/\.(mp4|webm)(\?|$)/i', $url) || (is_array($media) && stripos((string) ($media['type'] ?? ''), 'video') !== false))) {
                $videoUrl = $url;
                break;
            }
        }
        $videoUrl ??= $item?->video_url;
        $videoUrl = PimMediaUrl::toPublicUrl($videoUrl);

        if (empty($aiSummary)) {
            $aiSummary = "Produk {$product->name} merupakan salah satu perlengkapan unggulan EIGER yang menggabungkan durabilitas tangguh dan fungsionalitas tinggi untuk kenyamanan eksplorasi harian maupun petualangan teknis.";
        }

        // Fallback up to 5 similar products if not manually set (SRS FR-TABLE-04)
        if ($similarProducts->isEmpty()) {
            $similarProducts = Product::where('id', '!=', $product->id)
                ->where('is_discontinued', false)
                ->latest('id')
                ->limit(5)
                ->get();
        }

        $similarProducts->load(DeviceProductPayload::relations());
        $similarFormatted = $similarProducts
            ->map(fn (Product $similarProduct) => DeviceProductPayload::make($similarProduct))
            ->values();

        return response()->json([
            'status' => 'success',
            'matched' => true,
            'screen' => 'home',
            'data' => [
                'rfid_tag' => $rfidTag,
                'is_mapped_table' => (bool) $item,
                'activity_slug' => $item?->activity_slug,
                'ideal_for' => $idealFor ?: 'Aktivitas Outdoor & Penjelajahan Harian',
                'video_url' => $videoUrl,
                'ai_summary' => $aiSummary,
                'product' => DeviceProductPayload::make($product),
                'similar_products' => $similarFormatted,
            ],
        ]);
    }

    /**
     * Item removed from Table reader.
     * SRS FR-TABLE-01: Kembali ke Idle Screen saat item diangkat ("Item Lost").
     * POST /api/v1/table-expedition/item-lost
     */
    public function itemLost(Request $request): JsonResponse
    {
        $rfid = $request->input('rfid_tag') ?? $request->input('rfid');

        return response()->json([
            'status' => 'success',
            'event' => 'item_lost',
            'message' => 'Produk diangkat dari meja. Layar kembali ke Standby Screen.',
            'screen' => 'standby',
            'data' => [
                'action' => 'reset_to_standby',
                'previous_rfid' => $rfid,
            ],
        ]);
    }

    /**
     * Compare two products side-by-side with AI Summary comparison.
     * SRS Hal 13: Product Comparison and Suggestion dengan ringkasan komparasi berbasis AI.
     * POST /api/v1/table-expedition/compare
     */
    public function compare(Request $request): JsonResponse
    {
        $p1Id = $request->input('product_id_1');
        $p2Id = $request->input('product_id_2');

        if (! $p1Id && $request->filled('rfid_primary')) {
            $item1 = TableExpeditionItem::where('rfid_tag', $request->input('rfid_primary'))->first();
            $p1Id = $item1?->product_id;
        }

        if (! $p2Id && $request->filled('rfid_secondary')) {
            $item2 = TableExpeditionItem::where('rfid_tag', $request->input('rfid_secondary'))->first();
            $p2Id = $item2?->product_id;
        }

        if (! $p1Id || ! $p2Id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dibutuhkan 2 produk untuk komparasi (product_id_1 & product_id_2 atau rfid_primary & rfid_secondary).',
            ], 422);
        }

        $prod1 = Product::with(DeviceProductPayload::relations())->findOrFail($p1Id);
        $prod2 = Product::with(DeviceProductPayload::relations())->findOrFail($p2Id);

        $aiComparisonSummary = "Perbandingan {$prod1->name} dan {$prod2->name}: ";
        if ($prod1->price > $prod2->price) {
            $diff = number_format($prod1->price - $prod2->price, 0, ',', '.');
            $aiComparisonSummary .= "{$prod1->name} memiliki spesifikasi lebih premium (selisih Rp {$diff}), sedangkan {$prod2->name} menawarkan nilai ekonomis yang sangat baik untuk kebutuhan harian.";
        } else {
            $aiComparisonSummary .= 'Kedua produk saling melengkapi dengan karakteristik fungsional yang kuat untuk lini aktivitas penjelajahan EIGER.';
        }

        $data1 = DeviceProductPayload::make($prod1);
        $data2 = DeviceProductPayload::make($prod2);

        return response()->json([
            'status' => 'success',
            'data' => [
                'primary' => [
                    'rfid_tag' => $request->input('rfid_primary'),
                    'product' => $data1,
                ],
                'secondary' => [
                    'rfid_tag' => $request->input('rfid_secondary'),
                    'product' => $data2,
                ],
                'ai_comparison_summary' => $aiComparisonSummary,
            ],
        ]);
    }

    /**
     * Get system status and configuration stats for Table Expedition.
     * GET /api/v1/table-expedition/status
     */
    public function status(): JsonResponse
    {
        $activeCount = TableExpeditionItem::where('is_active', true)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'mode' => 'standby',
                'total_mapped_items' => TableExpeditionItem::count(),
                'active_items' => $activeCount,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
