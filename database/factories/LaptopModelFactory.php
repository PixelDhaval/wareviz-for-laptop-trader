<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\LaptopModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaptopModel>
 */
class LaptopModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'name' => fake()->bothify('####'),
        ];
    }
}
