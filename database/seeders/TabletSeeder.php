<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tablet;
use App\Models\TabletConfigVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TabletSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::where('is_discontinued', false)->take(5)->get();
        if ($products->isEmpty()) {
            return;
        }

        $featured = $products->firstWhere('is_featured', true) ?? $products->first();
        $tablet = Tablet::firstOrCreate(
            ['slug' => 'lobby-01'],
            [
                'name' => 'Lobby Tablet 01',
                'location' => 'Main Lobby',
                'featured_product_id' => $featured->id,
                'activation_code_hash' => Hash::make('LOBBY-01'),
                'is_active' => true,
            ],
        );

        if ($tablet->wasRecentlyCreated) {
            $recommendations = $products->where('id', '!=', $featured->id)->values();
            $tablet->recommendations()->sync(
                $recommendations->mapWithKeys(fn ($product, $index) => [$product->id => ['sort_order' => $index]])->all(),
            );
            TabletConfigVersion::create([
                'tablet_id' => $tablet->id,
                'version_number' => 1,
                'featured_product_id' => $featured->id,
                'recommendation_product_ids' => $recommendations->pluck('id')->all(),
            ]);
        }
    }
}
