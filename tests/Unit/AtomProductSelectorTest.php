<?php

namespace Tests\Unit;

use App\Services\AtomProductSelector;
use PHPUnit\Framework\TestCase;

class AtomProductSelectorTest extends TestCase
{
    public function test_it_builds_unique_gender_and_product_type_quotas(): void
    {
        $catalog = [];
        $sku = 910100000;
        foreach (['women' => 'WOMEN', 'men' => 'MEN'] as $gender => $marker) {
            foreach (['hat', 'shirt', 'jacket', 'bag', 'pants', 'shoes'] as $type) {
                for ($index = 1; $index <= 2; $index++) {
                    $catalog[] = $this->product((string) ++$sku, "{$marker} {$type} {$index}", $type);
                }
            }
        }
        $catalog[] = $this->product((string) ++$sku, 'JUNIOR hat', 'hat');

        $selected = (new AtomProductSelector)->select($catalog, 2);

        $this->assertCount(24, $selected);
        $this->assertCount(24, array_unique(array_column($selected, 'sku')));
        foreach (['women', 'men'] as $gender) {
            foreach (['hat', 'shirt', 'jacket', 'bag', 'pants', 'shoes'] as $type) {
                $this->assertCount(2, array_filter($selected, fn ($row) => $row['gender'] === $gender && $row['product_type'] === $type
                ));
            }
        }
        $this->assertNotContains('JUNIOR hat', array_column($selected, 'name'));
    }

    private function product(string $sku, string $name, string $type): array
    {
        [$category, $subCategory] = match ($type) {
            'hat' => ['Headwear', 'Caps'],
            'shirt' => ['Apparel', 'Kemeja'],
            'jacket' => ['Apparel', 'Jacket'],
            'bag' => ['Bags', 'Backpack'],
            'pants' => ['Apparel', 'Pants'],
            'shoes' => ['Footwear', 'Sepatu'],
        };

        return [
            'skuProduct' => $sku,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'soldCount' => 1,
            'activity' => ['name' => 'Daily Wear'],
            'category' => ['name' => $category],
            'subCategory' => ['name' => $subCategory],
        ];
    }
}
