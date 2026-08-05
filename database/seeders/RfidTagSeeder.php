<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\RfidTag;
use Illuminate\Database\Seeder;

class RfidTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 20 RFID tags linked to random products.
     * Uses unique product selection to avoid duplicate tags per product.
     */
    public function run(): void
    {
        $products = Product::inRandomOrder()->take(20)->get();

        foreach ($products as $product) {
            RfidTag::factory()->create([
                'product_id' => $product->id,
            ]);
        }
    }
}
