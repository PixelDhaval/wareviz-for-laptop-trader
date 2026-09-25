<?php

namespace Database\Factories;

use App\Enums\LaptopStatus;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Every SaleItem must belong to a laptop that is (at the moment of
        // creation) in stock, since SaleItem::created() moves it to Sold.
        $sale = Sale::factory()->create();

        return [
            'sale_id' => $sale,
            'laptop_id' => Laptop::factory()->state(['status' => LaptopStatus::InStock]),
            'price' => fake()->randomFloat(2, 50, 500),
            'price_currency_id' => $sale->currency_id,
            'price_exchange_rate' => $sale->exchange_rate,
        ];
    }

    /**
     * Set a price in a specific currency, converted at a specific rate — for
     * items that deviate from their sale's default currency.
     */
    public function withPrice(string $amount, Currency $currency, string $exchangeRate): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $amount,
            'price_currency_id' => $currency->id,
            'price_exchange_rate' => $exchangeRate,
        ]);
    }
}
