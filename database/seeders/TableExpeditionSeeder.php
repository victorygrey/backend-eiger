<?php

namespace Database\Seeders;

use App\Models\TableExpeditionConfig;
use Illuminate\Database\Seeder;

class TableExpeditionSeeder extends Seeder
{
    public function run(): void
    {
        TableExpeditionConfig::set('standby_title', 'EIGER Table Expedition Hub');
        TableExpeditionConfig::set('standby_subtitle', 'Letakkan produk ber-tag RFID di atas meja untuk melihat spesifikasi detail dan komparasi.');
        TableExpeditionConfig::set('usage_instructions', [
            'Ambil produk dari display yang memiliki tag RFID EIGER.',
            'Letakkan produk tepat di atas permukaan scanner meja ekspedisi.',
            'Layar akan otomatis menampilkan spesifikasi teknis, fitur, ukuran, warna, dan video dalam 1-2 detik.',
            'Pilih produk relevan pada layar untuk membandingkan detail produk.',
            'Angkat produk saat selesai untuk mengembalikan layar ke mode standby.',
        ]);
    }
}
