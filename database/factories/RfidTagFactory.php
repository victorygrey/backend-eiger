<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RfidTag>
 */
class RfidTagFactory extends Factory
{
    /**
     * Define the model's default state.
     * Generates a realistic RFID UID in hex format (e.g., A3:4F:12:89).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid'        => strtoupper(implode(':', array_map(
                fn() => sprintf('%02X', random_int(0, 255)),
                range(0, 3)
            ))),
            'product_id' => Product::inRandomOrder()->first()?->id,
        ];
    }
}
