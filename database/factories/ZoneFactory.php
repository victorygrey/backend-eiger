<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $zones = [
            'Zone Pakaian Pria',
            'Zone Pakaian Wanita',
            'Zone Sepatu & Alas Kaki',
            'Zone Tas & Aksesoris',
            'Zone Perlengkapan Outdoor',
            'Zone Peralatan Mendaki',
            'Zone Perlengkapan Camping',
            'Zone Elektronik & Gadget',
        ];

        static $index = 0;

        return [
            'name'        => $zones[$index % count($zones)] . ' ' . ($index > 7 ? fake()->unique()->word() : ''),
            'description' => fake()->sentence(10),
        ];

        $index++;
    }
}
