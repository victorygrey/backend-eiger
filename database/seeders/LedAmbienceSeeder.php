<?php

namespace Database\Seeders;

use App\Models\LedAmbienceItem;
use App\Models\LedAmbienceTemplate;
use App\Models\Product;
use Illuminate\Database\Seeder;

class LedAmbienceSeeder extends Seeder
{
    public function run(): void
    {
        $descriptions = [
            'idle' => 'Suasana default ketika tidak ada RFID produk yang sedang terdeteksi.',
            'mountaineering' => 'Suasana aktivitas gunung, camping, hiking, climbing, dan running.',
            'lifestyle' => 'Suasana untuk daily wear, travelling, commute, dan aktivitas keseharian.',
            'tactical' => 'Suasana tegas untuk tactical, combat, range, dan daily mission.',
            'riding' => 'Suasana perjalanan untuk riding, day ride, touring, dan cycling.',
        ];

        foreach ((array) config('led_ambience.templates') as $key => $definition) {
            LedAmbienceTemplate::updateOrCreate(
                ['template_key' => $key],
                [
                    'name' => $definition['name'],
                    'lighting_color' => $definition['lighting_color'],
                    'description' => $descriptions[$key],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        foreach (Product::take(2)->get() as $index => $product) {
            LedAmbienceItem::updateOrCreate(
                ['rfid_tag' => sprintf('E280116060000204689000%02d', $index + 1)],
                [
                    'product_id' => $product->id,
                    'notes' => 'Sample RFID LED Ambience',
                    'is_active' => true,
                ],
            );
        }
    }
}
