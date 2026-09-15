<?php

namespace Database\Seeders;

use App\Models\FitAndGoActivity;
use App\Models\FitAndGoCategory;
use App\Models\FitAndGoDevice;
use Illuminate\Database\Seeder;

class FitAndGoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Initial Kiosk Device
        FitAndGoDevice::updateOrCreate(
            ['device_code' => 'fit-kiosk-01'],
            [
                'name'              => 'Kiosk Virtual Fitting 01',
                'location'          => 'Lantai 1 - Area Fitting Room',
                'ip_address'        => '192.168.18.51',
                'gpu_endpoint'      => 'http://192.168.18.50:8080',
                'camera_source'     => 'DSLR Kiosk Cam 01',
                'status'            => 'online',
                'is_active'         => true,
                'last_heartbeat_at' => now(),
            ]
        );

        // 2. Initial EIGER Activities (SRS Hal 10: data activity dari Care MC Level 2 + gambar)
        $activities = [
            [
                'name'            => 'Running',
                'slug'            => 'running',
                'care_mc_level_2' => 'A03006',
                'image'           => 'https://images.unsplash.com/photo-1552674605-db6ffd4facb5?auto=format&fit=crop&w=800&q=80',
                'description'     => 'Aktivitas lari dan olahraga luar ruang.',
                'sort_order'      => 3,
            ],
            [
                'name'            => 'Hiking',
                'slug'            => 'hiking',
                'care_mc_level_2' => 'A03001',
                'image'           => 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?auto=format&fit=crop&w=800&q=80',
                'description'     => 'Penjelajahan alam bebas, rimba, dan jalur perbukitan.',
                'sort_order'      => 2,
            ],
            [
                'name'            => 'Riding',
                'slug'            => 'riding',
                'care_mc_level_2' => 'A03008',
                'image'           => 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=800&q=80',
                'description'     => 'Petualangan touring dan berkendara roda dua jarak jauh.',
                'sort_order'      => 4,
            ],
            [
                'name'            => 'Camping',
                'slug'            => 'camping',
                'care_mc_level_2' => 'A02001',
                'image'           => 'https://images.unsplash.com/photo-1510312305653-8ed496efae75?auto=format&fit=crop&w=800&q=80',
                'description'     => 'Rekreasi bermalam di alam terbuka dan api unggun.',
                'sort_order'      => 1,
            ],
            [
                'name'            => 'Travelling',
                'slug'            => 'travelling',
                'care_mc_level_2' => 'A01001',
                'image'           => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?auto=format&fit=crop&w=800&q=80',
                'description'     => 'Perjalanan dan eksplorasi kota maupun alam.',
                'sort_order'      => 5,
            ],
        ];

        foreach ($activities as $act) {
            FitAndGoActivity::updateOrCreate(['slug' => $act['slug']], $act);
        }

        // 3. Initial Categories (SRS Hal 10 & 11)
        $categories = [
            [
                'code'             => 'hat',
                'name'             => 'Hat',
                'display_name'     => 'Topi / Headwear',
                'mc_level'         => 'mc 3',
                'mc_keywords'      => 'headwear, topi, cap, hat, beanie, buff',
                'background_image' => 'https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=1200&q=80',
                'icon'             => 'bi-smartwatch',
                'sort_order'       => 1,
            ],
            [
                'code'             => 'apparel',
                'name'             => 'Apparel',
                'display_name'     => 'Baju / Atasan',
                'mc_level'         => 'mc 4',
                'mc_keywords'      => 'apparel, shirt, top, jacket, sweater, vest, jaket, kaos, polo, kemeja, hoodie',
                'background_image' => 'https://images.unsplash.com/photo-1486870591958-9b9d0d1dda99?auto=format&fit=crop&w=1200&q=80',
                'icon'             => 'bi-person',
                'sort_order'       => 2,
            ],
            [
                'code'             => 'pants',
                'name'             => 'Pants',
                'display_name'     => 'Celana / Bawahan',
                'mc_level'         => 'mc 4',
                'mc_keywords'      => 'pants, short, celana, shorts, cargo',
                'background_image' => 'https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1200&q=80',
                'icon'             => 'bi-arrows-vertical',
                'sort_order'       => 3,
            ],
            [
                'code'             => 'footwear',
                'name'             => 'Footwear',
                'display_name'     => 'Sepatu / Alas Kaki',
                'mc_level'         => 'mc 3',
                'mc_keywords'      => 'footwear, sepatu, sandal, boot, shoe, boots',
                'background_image' => 'https://images.unsplash.com/photo-1470246973918-29a93221c455?auto=format&fit=crop&w=1200&q=80',
                'icon'             => 'bi-box',
                'sort_order'       => 4,
            ],
        ];

        foreach ($categories as $cat) {
            FitAndGoCategory::updateOrCreate(['code' => $cat['code']], $cat);
        }
    }
}
