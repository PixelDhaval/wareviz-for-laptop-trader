<?php

namespace Database\Factories;

use App\Enums\LaptopStatus;
use App\Models\Brand;
use App\Models\Generation;
use App\Models\Laptop;
use App\Models\LaptopModel;
use App\Models\Processor;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Laptop>
 */
class LaptopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'brand_id' => Brand::factory(),
            'laptop_model_id' => LaptopModel::factory(),
            'processor_id' => Processor::factory(),
            'serial_no' => (string) fake()->unique()->numberBetween(1, 999),
            'generation_id' => Generation::factory(),
            'ram_gb' => fake()->randomElement([4, 8, 16]),
            'storage_gb' => fake()->randomElement([64, 128, 256, 512]),
            'has_builtin_ram' => false,
            'is_battery_ok' => true,
            'is_lcd_ok' => true,
            'is_bezel_ok' => true,
            'is_top_cover_ok' => true,
            'is_body_ok' => true,
            'is_back_cover_ok' => true,
            'is_keyboard_ok' => true,
            'is_touchpad_ok' => true,
            'issues' => null,
            'status' => LaptopStatus::InStock,
        ];
    }
}
