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
        foreach (Tablet::STATIC_SLOTS as $slotNumber => $slot) {
            $activationCode = 'TABLET-0'.$slotNumber;
            $tablet = Tablet::firstOrCreate(
                ['slug' => $slot['slug']],
                [
                    ...$slot,
                    'featured_product_id' => $featured->id,
                    'activation_code_hash' => Hash::make($activationCode),
                    'activation_code_lookup_hash' => Tablet::activationCodeLookupHash($activationCode),
                    'activation_code_encrypted' => $activationCode,
                    'is_active' => true,
                ],
            );

            if (! $tablet->wasRecentlyCreated && $tablet->featured_product_id) {
                continue;
            }

            $tablet->forceFill(['featured_product_id' => $featured->id])->save();
            $recommendations = $products->where('id', '!=', $featured->id)->values();
            $tablet->recommendations()->sync(
                $recommendations->mapWithKeys(fn ($product, $index) => [$product->id => ['sort_order' => $index]])->all(),
            );
            TabletConfigVersion::updateOrCreate(
                [
                    'tablet_id' => $tablet->id,
                    'version_number' => 1,
                ],
                [
                    'featured_product_id' => $featured->id,
                    'recommendation_product_ids' => $recommendations->pluck('id')->all(),
                ],
            );
        }
    }
}
