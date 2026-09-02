<?php

namespace Database\Factories;

use App\Models\Processor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Processor>
 */
class ProcessorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['i3', 'i5', 'i7', 'Ryzen 3', 'Ryzen 5', 'Ryzen 5 Pro', 'Ryzen 7', 'Ryzen 7 Pro', 'N4120', 'vPro', 'Chromebook']),
        ];
    }
}
