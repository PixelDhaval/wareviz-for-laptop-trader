<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->unique()->words(2, true),
            'symbol' => null,
            'exchange_rate' => fake()->randomFloat(6, 0.01, 200),
            'is_base' => false,
        ];
    }

    /**
     * Indicate that the currency is the base currency costs are converted into.
     */
    public function base(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_base' => true,
            'exchange_rate' => 1,
        ]);
    }
}
