<?php

namespace Database\Factories;

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Agency;
use App\Models\Laptop;
use App\Models\RepairJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairJob>
 */
class RepairJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assignee = fake()->randomElement(JobAssignee::cases());

        return [
            'laptop_id' => Laptop::factory(),
            'type' => fake()->randomElement(JobType::cases()),
            'assignee' => $assignee,
            'agency_id' => $assignee === JobAssignee::Agency ? Agency::factory() : null,
            'cost' => fake()->optional()->randomFloat(2, 10, 200),
            'status' => JobStatus::Pending,
            'sent_at' => now()->toDateString(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
