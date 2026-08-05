<?php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 8 predefined zones representing physical areas in EIGER Digital Store.
     */
    public function run(): void
    {
        $zones = [
            [
                'name'        => 'Zone Pakaian Pria',
                'description' => 'Area display koleksi pakaian pria EIGER termasuk jaket, kaos, dan celana outdoor.',
            ],
            [
                'name'        => 'Zone Pakaian Wanita',
                'description' => 'Area display koleksi pakaian wanita EIGER termasuk jaket, kaos, dan celana outdoor.',
            ],
            [
                'name'        => 'Zone Sepatu & Alas Kaki',
                'description' => 'Area display koleksi sepatu hiking, sandal, dan alas kaki outdoor EIGER.',
            ],
            [
                'name'        => 'Zone Tas & Aksesoris',
                'description' => 'Area display koleksi tas ransel, tas selempang, dan aksesoris perjalanan EIGER.',
            ],
            [
                'name'        => 'Zone Perlengkapan Outdoor',
                'description' => 'Area display perlengkapan outdoor umum termasuk kacamata, sarung tangan, dan topi.',
            ],
            [
                'name'        => 'Zone Peralatan Mendaki',
                'description' => 'Area display peralatan khusus pendakian termasuk headlamp, kompas, dan alat navigasi.',
            ],
            [
                'name'        => 'Zone Perlengkapan Camping',
                'description' => 'Area display perlengkapan berkemah termasuk tenda, sleeping bag, dan matras.',
            ],
            [
                'name'        => 'Zone Elektronik & Gadget',
                'description' => 'Area display produk elektronik dan gadget outdoor termasuk GPS, kamera aksi, dan powerbank.',
            ],
        ];

        foreach ($zones as $zone) {
            Zone::create($zone);
        }
    }
}
