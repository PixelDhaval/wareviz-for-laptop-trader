<?php

namespace Database\Factories;

use App\Models\Generation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['4th', '6th', '7th', '8th', '10th', '11th', '12th', '13th']),
        ];
    }
}
