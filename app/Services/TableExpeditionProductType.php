<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class TableExpeditionProductType
{
    /** Product kinds use master subcategories first, not activity or product names. */
    public function resolve(Product $product): ?array
    {
        $product->loadMissing(['atomCategory', 'atomSubCategory']);
        $sub = $product->atomSubCategory?->name;
        $label = $sub ?: ($product->category ?: $product->atomCategory?->name);
        if (! is_string($label) || trim($label) === '') {
            return null;
        }

        $name = Str::slug($label);
        $families = [
            'shoes' => ['Sepatu', ['sepatu', 'sepatu-boots', 'sepatu-pria', 'sepatu-wanita', 'shoe', 'shoes', 'boot', 'boots', 'sneaker', 'sneakers']],
            'sandals' => ['Sandal', ['sandal', 'sandals']],
            'jackets' => ['Jaket', ['jacket', 'jackets', 'jaket', 'sweater', 'vest', 'rompi', 'hoodie', 'outerwear']],
            'shirts' => ['Shirt / Kaos', ['kemeja', 'shirt', 'shirts', 't-shirt', 't-shirts', 'kaos', 'tee', 'tees', 'polo', 'top', 'atasan']],
            'pants' => ['Celana', ['pants', 'pant', 'celana', 'celana-pendek', 'short', 'shorts', 'trouser', 'trousers', 'bottom']],
            'hats' => ['Topi', ['headwear', 'hat', 'hats', 'cap', 'caps', 'beanie', 'beanies', 'topi', 'bandana']],
            'bags' => ['Tas', ['bag', 'bags', 'tas', 'backpack', 'rucksack', 'carrier', 'tas-ransel', 'shoulder-bag', 'tas-selempang', 'tas-pinggang', 'waist-bag', 'tas-duffle', 'duffel', 'duffle', 'tas-tangan', 'hand-bag', 'tas-motor', 'tas-sepeda', 'pouch', 'trolley-bag', 'koper', 'luggage']],
        ];
        foreach ($families as $key => [$familyLabel, $names]) {
            if (in_array($name, $names, true)) {
                return ['key' => $key, 'label' => $familyLabel];
            }
        }

        // Other known kinds stay comparable by their actual classification.
        return ['key' => ($sub ? 'subcategory:' : 'category:').$name, 'label' => trim($label)];
    }

    public function matches(Product $primary, Product $candidate): bool
    {
        $type = $this->resolve($primary);

        return ! $primary->is($candidate) && $type !== null
            && $type['key'] === ($this->resolve($candidate)['key'] ?? null);
    }
}
