<?php

use App\Enums\JobAssignee;
use App\Enums\JobType;
use App\Enums\LaptopStatus;
use App\Filament\Resources\Laptops\Pages\ViewLaptop;
use App\Models\Laptop;
use App\Models\RepairJob;
use App\Models\User;
use Livewire\Livewire;

test('the view page loads and shows the laptop summary', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    Livewire::test(ViewLaptop::class, ['record' => $laptop->getKey()])
        ->assertOk()
        ->assertActionVisible('sendForJob')
        ->assertActionHidden('completeJob');
});

test('sending a laptop for repair from the view page creates a job and hides the action', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    Livewire::test(ViewLaptop::class, ['record' => $laptop->getKey()])
        ->callAction('sendForJob', data: [
            'type' => JobType::Repair->value,
            'assignee' => JobAssignee::InHouse->value,
        ])
        ->assertHasNoFormErrors()
        ->assertActionHidden('sendForJob')
        ->assertActionVisible('completeJob')
        ->assertSee(LaptopStatus::InRepair->getLabel());

    expect(RepairJob::query()->where('laptop_id', $laptop->id)->count())->toBe(1)
        ->and($laptop->fresh()->status)->toBe(LaptopStatus::InRepair);
});
