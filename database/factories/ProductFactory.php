<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * EIGER product categories for realistic dummy data.
     */
    private array $categories = [
        'Jaket', 'Kaos', 'Celana', 'Sepatu', 'Sandal',
        'Tas Ransel', 'Tas Selempang', 'Topi', 'Kacamata',
        'Sarung Tangan', 'Kaos Kaki', 'Ikat Pinggang',
        'Tenda', 'Sleeping Bag', 'Matras', 'Kompas',
        'Headlamp', 'Botol Minum', 'Jas Hujan', 'Pelampung',
    ];

    private array $materials = [
        'Polyester', 'Nylon', 'Cotton', 'Gore-Tex',
        'Spandex', 'Ripstop Nylon', 'Merino Wool',
        'Cordura', 'Fleece', 'Microfiber',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement($this->categories);
        $skuPrefix = strtoupper(substr(str_replace(' ', '', $category), 0, 3));

        return [
            'sku'             => $skuPrefix . '-' . fake()->unique()->numerify('####'),
            'name'            => 'EIGER ' . $category . ' ' . fake()->word(),
            'price'           => fake()->randomElement([
                199000, 249000, 299000, 349000, 399000,
                449000, 499000, 549000, 599000, 699000,
                749000, 799000, 849000, 899000, 999000,
            ]),
            'stock'           => fake()->numberBetween(0, 150),
            'zone_id'         => Zone::inRandomOrder()->first()?->id,
            'image'           => null,
            'material'        => fake()->randomElement($this->materials),
            'description'     => fake()->paragraph(3),
            'is_featured'     => fake()->boolean(20),
            'is_discontinued' => fake()->boolean(10),
        ];
    }
}
