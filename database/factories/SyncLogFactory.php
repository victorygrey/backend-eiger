<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SyncLog>
 */
class SyncLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sources = ['CARE', 'PIM', 'Loyalty'];
        $statuses = ['success', 'failed', 'pending'];

        $status = fake()->randomElement($statuses);
        $messages = [
            'success' => 'Synchronization completed successfully. ' . fake()->numberBetween(10, 200) . ' records synced.',
            'failed'  => 'Synchronization failed: ' . fake()->sentence(5),
            'pending' => 'Synchronization is pending. Waiting for external system response.',
        ];

        return [
            'source'    => fake()->randomElement($sources),
            'status'    => $status,
            'message'   => $messages[$status],
            'synced_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
