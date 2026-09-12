<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku'        => fake()->unique()->numerify('91000######'),
            'name'       => fake()->words(3, true),
            'color'      => fake()->safeColorName(),
            'size'       => fake()->randomElement(['S', 'M', 'L', 'XL', '28', '30', '32']),
            'price'      => fake()->randomElement([149000, 199000, 249000, 299000, 399000, 499000]),
            'stock'      => fake()->numberBetween(0, 50),
        ];
    }
}
