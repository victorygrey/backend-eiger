<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\TableExpeditionConfig;
use App\Models\TableExpeditionItem;
use Illuminate\Database\Seeder;

class TableExpeditionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Standby & Usage Instructions Configuration (SRS FR-TABLE-01 & FR-TABLE-07)
        TableExpeditionConfig::set('standby_title', 'EIGER Table Expedition Hub');
        TableExpeditionConfig::set('standby_subtitle', 'Letakkan produk ber-tag RFID di atas meja untuk melihat spesifikasi detail dan komparasi.');
        TableExpeditionConfig::set('usage_instructions', json_encode([
            'Ambil produk dari display yang memiliki tag RFID EIGER.',
            'Letakkan produk tepat di atas permukaan scanner meja ekspedisi.',
            'Layar akan otomatis menampilkan spesifikasi teknis, fitur, ukuran, warna, dan video dalam 1-2 detik.',
            'Pilih produk serupa pada layar untuk membandingkan spesifikasi dan melihat ringkasan AI.',
            'Angkat produk saat selesai untuk mengembalikan layar ke mode standby.',
        ]));

        // 2. Initial Sample Items mapped to Table Expedition
        $products = Product::take(6)->get();
        if ($products->count() >= 2) {
            $mainProd = $products[0];
            $similarIds = $products->slice(1, 4)->pluck('id')->all();

            TableExpeditionItem::updateOrCreate(
                ['rfid_tag' => 'E28011606000020468900001'],
                [
                    'product_id'          => $mainProd->id,
                    'activity_slug'       => 'mountaineering',
                    'ideal_for'           => 'High Alpine Expeditions, Mountaineering, Extreme Winter Trekking',
                    'video_url'           => 'https://assets.mixkit.co/videos/preview/mixkit-snow-capped-mountains-in-winter-40087-large.mp4',
                    'features'            => [
                        'Teknologi Tropic Waterproof tahan air dengan rating 20.000mm hydrostatic head',
                        'Sistem ventilasi ketiak (pit zips) dua arah untuk sirkulasi udara maksimal',
                        'Kapucon kompatibel dengan helm panjat dilengkapi drawcord adjuster',
                        'Resleting tahan air YKK AquaGuard® di semua kantung eksterior',
                    ],
                    'technical_details'   => [
                        'Weight'     => '580 gram',
                        'Material'   => '3-Layer Ripstop Nylon with GORE-TEX Membrane',
                        'Breathability' => '15.000 g/m²/24h',
                        'Warranty'   => 'Garansi Seumur Hidup EIGER Adventure',
                    ],
                    'ai_summary'          => 'Perlengkapan teknis dirancang khusus untuk menghadapi kondisi cuaca ekstrem di pegunungan tinggi, menggabungkan ketahanan abrasi tingkat tinggi dan bobot yang ringan.',
                    'similar_product_ids' => $similarIds,
                    'notes'               => 'Sample produk unggulan meja ekspedisi',
                    'is_active'           => true,
                ]
            );
        }
    }
}
