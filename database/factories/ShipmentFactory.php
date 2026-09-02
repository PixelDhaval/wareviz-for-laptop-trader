<?php

namespace Database\Factories;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SHP-####??'),
            'name' => fake()->optional()->company(),
            'received_at' => fake()->optional()->date(),
            'is_completed' => false,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
