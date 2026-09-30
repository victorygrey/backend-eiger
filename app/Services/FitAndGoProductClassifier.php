<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class FitAndGoProductClassifier
{
    /** @return array<string, array{label: string, icon: string}> */
    public function definitions(): array
    {
        return [
            'hat' => ['label' => 'Head Accessories', 'icon' => 'bi-sunglasses'],
            'apparel' => ['label' => 'Top', 'icon' => 'bi-person-standing-dress'],
            'pants' => ['label' => 'Bottom', 'icon' => 'bi-universal-access'],
            'bags' => ['label' => 'Bags', 'icon' => 'bi-handbag'],
            'footwear' => ['label' => 'Footwear', 'icon' => 'bi-stars'],
        ];
    }

    public function groupCode(Product $product): string
    {
        $source = Str::lower(implode(' ', array_filter([
            $product->atomSubCategory?->name,
            $product->atomCategory?->name,
            $product->category,
            $product->name,
        ])));

        $groups = [
            'hat' => ['headwear', 'head gear', 'hat', 'cap', 'beanie', 'balaclava'],
            'bags' => ['bag', 'backpack', 'rucksack', 'duffel', 'carrier', 'pouch'],
            'footwear' => ['footwear', 'shoe', 'shoes', 'sandal', 'boot'],
            'pants' => ['bottom', 'pant', 'pants', 'trouser', 'short', 'skirt'],
            'apparel' => ['apparel', 'top', 'shirt', 't-shirt', 'jacket', 'shell', 'hoodie', 'vest', 'jersey', 'outerwear'],
        ];

        foreach ($groups as $group => $keywords) {
            if (Str::contains($source, $keywords)) {
                return $group;
            }
        }

        return 'unmapped';
    }
}
