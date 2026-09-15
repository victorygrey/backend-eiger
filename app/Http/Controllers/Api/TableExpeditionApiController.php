<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RfidTag;
use App\Models\TableExpeditionConfig;
use App\Models\TableExpeditionItem;
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
            'data'   => [
                'title'              => $title,
                'subtitle'           => $subtitle,
                'instructions'       => $instructions,
                'usage_instructions' => $instructions,
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
        if (!$tagInput) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tag RFID wajib diisi (parameter rfid_tag atau rfid).',
            ], 422);
        }

        $rfidTag = trim(strtoupper($tagInput));

        // 1. Check custom Table Expedition mapping
        $item = TableExpeditionItem::with(['product.zone', 'product.variants'])
            ->where('rfid_tag', $rfidTag)
            ->where('is_active', true)
            ->first();

        $product = null;
        $idealFor = null;
        $videoUrl = null;
        $features = [];
        $technicalDetails = [];
        $aiSummary = null;
        $similarProducts = collect();

        if ($item && $item->product) {
            $product = $item->product;
            $idealFor = $item->ideal_for;
            $videoUrl = $item->video_url;
            $features = $item->features ?: [];
            $technicalDetails = $item->technical_details ?: [];
            $aiSummary = $item->ai_summary;
            $similarProducts = $item->similar_products;

            $item->update(['last_scanned_at' => now()]);
        } else {
            // Fallback to general rfid_tags
            $generalTag = RfidTag::with(['product.zone', 'product.variants'])
                ->where('uid', $rfidTag)
                ->first();

            if ($generalTag && $generalTag->product) {
                $product = $generalTag->product;
            }
        }

        if (!$product) {
            return response()->json([
                'status'   => 'error',
                'matched'  => false,
                'message'  => "Tag RFID '{$rfidTag}' tidak terdaftar pada modul Table Expedition.",
                'rfid_tag' => $rfidTag,
            ], 404);
        }

        // Available sizes & colors from variants (SRS FR-TABLE-03)
        $variants = $product->variants ?? collect();
        $availableSizes = $variants->pluck('size')->filter()->unique()->values()->all();
        $availableColors = $variants->pluck('color')->filter()->unique()->values()->all();

        if (empty($features)) {
            if (!empty($product->technologies)) {
                $features = array_map(fn($t) => ($t['name'] ?? 'TEKNOLOGI') . ': ' . ($t['description'] ?? ''), $product->technologies);
            } else {
                $features = [
                    'Bahan dirancang khusus untuk kenyamanan dan ketahanan maksimal aktivitas luar ruang.',
                    'Konstruksi ergonomis mendukung pergerakan dinamis pengguna.',
                    'Dilengkapi logo dan grafis autentik khas EIGER Adventure.',
                ];
            }
        }

        if (empty($technicalDetails)) {
            $technicalDetails = [
                'Material' => $product->material ?: 'Polyester / Technical Fabric',
                'Zone'     => $product->zone?->name ?? 'General Outdoor',
                'SKU'      => $product->sku,
            ];
            foreach ($product->specifications as $spec) {
                $technicalDetails[$spec['name'] ?? $spec['code']] = $spec['value'];
            }
            if ($product->weight) {
                $technicalDetails['Berat'] = $product->weight . ' gram';
            }
            foreach ($product->custom_attributes_list as $ca) {
                if (in_array(strtolower($ca['attributeCode']), ['waterproof', 'breathability', 'dimension', 'gender'])) {
                    $technicalDetails[ucfirst(str_replace('_', ' ', $ca['attributeCode']))] = strip_tags($ca['value']);
                }
            }
        }

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

        $similarFormatted = $similarProducts->map(fn ($p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'sku'         => $p->sku,
            'price'       => (float) $p->price,
            'stock'       => (int) $p->stock,
            'image'       => $p->image,
            'category'    => $p->category,
        ])->values();

        return response()->json([
            'status'  => 'success',
            'matched' => true,
            'screen'  => 'home',
            'data'    => [
                'rfid_tag'           => $rfidTag,
                'is_mapped_table'    => (bool) $item,
                'activity_slug'      => $item?->activity_slug,
                'ideal_for'          => $idealFor ?: 'Aktivitas Outdoor & Penjelajahan Harian',
                'video_url'          => $videoUrl,
                'features'           => $features,
                'technical_details'  => $technicalDetails,
                'ai_summary'         => $aiSummary,
                'variants'           => [
                    'sizes'  => $availableSizes,
                    'colors' => $availableColors,
                ],
                'product'            => [
                    'id'               => $product->id,
                    'name'             => $product->name,
                    'sku'              => $product->sku,
                    'price'            => (float) $product->price,
                    'stock'            => (int) $product->stock,
                    'image'            => $product->image,
                    'category'         => $product->category,
                    'description'      => $product->description,
                    'zone'             => $product->zone?->name,
                    'technologies'     => $product->technologies,
                    'activities'       => $product->activities,
                    'specifications'   => $product->specifications,
                    'custom_attributes'=> $product->custom_attributes_list,
                    'weight'           => $product->weight,
                    'available_sizes'  => $availableSizes,
                    'available_colors' => $availableColors,
                ],
                'similar_products'   => $similarFormatted,
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
            'status'  => 'success',
            'event'   => 'item_lost',
            'message' => 'Produk diangkat dari meja. Layar kembali ke Standby Screen.',
            'screen'  => 'standby',
            'data'    => [
                'action'        => 'reset_to_standby',
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

        if (!$p1Id && $request->filled('rfid_primary')) {
            $item1 = TableExpeditionItem::where('rfid_tag', $request->input('rfid_primary'))->first();
            $p1Id = $item1?->product_id;
        }

        if (!$p2Id && $request->filled('rfid_secondary')) {
            $item2 = TableExpeditionItem::where('rfid_tag', $request->input('rfid_secondary'))->first();
            $p2Id = $item2?->product_id;
        }

        if (!$p1Id || !$p2Id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Dibutuhkan 2 produk untuk komparasi (product_id_1 & product_id_2 atau rfid_primary & rfid_secondary).',
            ], 422);
        }

        $prod1 = Product::with('zone')->findOrFail($p1Id);
        $prod2 = Product::with('zone')->findOrFail($p2Id);

        $aiComparisonSummary = "Perbandingan {$prod1->name} dan {$prod2->name}: ";
        if ($prod1->price > $prod2->price) {
            $diff = number_format($prod1->price - $prod2->price, 0, ',', '.');
            $aiComparisonSummary .= "{$prod1->name} memiliki spesifikasi lebih premium (selisih Rp {$diff}), sedangkan {$prod2->name} menawarkan nilai ekonomis yang sangat baik untuk kebutuhan harian.";
        } else {
            $aiComparisonSummary .= "Kedua produk saling melengkapi dengan karakteristik fungsional yang kuat untuk lini aktivitas penjelajahan EIGER.";
        }

        $data1 = [
            'id'          => $prod1->id,
            'name'        => $prod1->name,
            'sku'         => $prod1->sku,
            'price'       => (float) $prod1->price,
            'material'    => $prod1->material ?: 'Technical Fabric',
            'zone'        => $prod1->zone?->name,
            'image'       => $prod1->image,
            'description' => $prod1->description,
        ];

        $data2 = [
            'id'          => $prod2->id,
            'name'        => $prod2->name,
            'sku'         => $prod2->sku,
            'price'       => (float) $prod2->price,
            'material'    => $prod2->material ?: 'Technical Fabric',
            'zone'        => $prod2->zone?->name,
            'image'       => $prod2->image,
            'description' => $prod2->description,
        ];

        return response()->json([
            'status' => 'success',
            'data'   => [
                'product_1'             => $data1,
                'product_2'             => $data2,
                'primary'               => [
                    'rfid_tag' => $request->input('rfid_primary'),
                    'product'  => $data1,
                ],
                'secondary'             => [
                    'rfid_tag' => $request->input('rfid_secondary'),
                    'product'  => $data2,
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
            'data'   => [
                'mode'               => 'standby',
                'total_mapped_items' => TableExpeditionItem::count(),
                'active_items'       => $activeCount,
                'active_items_count' => $activeCount,
                'server_time'        => now()->toIso8601String(),
            ],
        ]);
    }
}
