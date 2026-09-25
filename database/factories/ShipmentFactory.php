<?php

namespace Database\Factories;

use App\Enums\ShipmentCostType;
use App\Models\Currency;
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

    /**
     * Set one cost line: an amount in the given currency, converted to the
     * base currency at the given exchange rate.
     */
    public function withCost(ShipmentCostType $type, Currency $currency, string $amount, string $exchangeRate): static
    {
        return $this->state(fn (array $attributes) => [
            $type->value => $amount,
            $type->currencyColumn() => $currency->id,
            $type->exchangeRateColumn() => $exchangeRate,
        ]);
    }
}
