<?php

namespace Database\Factories;

use App\Enums\SaleType;
use App\Models\Buyer;
use App\Models\Currency;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(SaleType::cases()),
            'buyer_id' => Buyer::factory(),
            'currency_id' => Currency::factory(),
            'exchange_rate' => fake()->randomFloat(6, 1, 100),
            'sold_at' => now()->toDateString(),
            'is_completed' => false,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the sale is an export sale, with the fields that trip
     * become required.
     */
    public function export(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SaleType::Export,
            'destination_country' => fake()->country(),
            'reference_no' => fake()->bothify('INV-####'),
        ]);
    }
}
