<?php

use App\Enums\JobAssignee;
use App\Enums\JobType;
use App\Filament\Resources\RepairJobs\Pages\CreateRepairJob;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\RepairJob;
use App\Models\User;
use Livewire\Livewire;

test('a super_admin can create a repair job with an expense in a chosen currency', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $laptop = Laptop::factory()->create();
    $usd = Currency::factory()->create();

    Livewire::test(CreateRepairJob::class)
        ->fillForm([
            'laptop_id' => $laptop->id,
            'type' => JobType::Repair->value,
            'assignee' => JobAssignee::InHouse->value,
            'cost' => '100',
            'cost_currency_id' => $usd->id,
            'cost_exchange_rate' => '83.5',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $job = RepairJob::query()->where('laptop_id', $laptop->id)->sole();

    expect($job->cost)->toBe('100.00')
        ->and($job->cost_currency_id)->toBe($usd->id)
        ->and($job->cost_exchange_rate)->toBe('83.500000')
        ->and($job->cost_in_base_currency)->toBe('8350.00000000');
});

test('a repair job can be created with no expense', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $laptop = Laptop::factory()->create();

    Livewire::test(CreateRepairJob::class)
        ->fillForm([
            'laptop_id' => $laptop->id,
            'type' => JobType::Repair->value,
            'assignee' => JobAssignee::InHouse->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $job = RepairJob::query()->where('laptop_id', $laptop->id)->sole();

    expect($job->cost)->toBeNull()
        ->and($job->cost_currency_id)->toBeNull()
        ->and($job->cost_exchange_rate)->toBe('1.000000')
        ->and($job->cost_in_base_currency)->toBe('0.00000000');
});

test('a repair job expense requires a currency and a positive exchange rate', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $laptop = Laptop::factory()->create();
    $usd = Currency::factory()->create();

    Livewire::test(CreateRepairJob::class)
        ->fillForm([
            'laptop_id' => $laptop->id,
            'type' => JobType::Repair->value,
            'assignee' => JobAssignee::InHouse->value,
            'cost' => '100',
        ])
        ->call('create')
        ->assertHasFormErrors(['cost_currency_id' => 'required', 'cost_exchange_rate' => 'required']);

    Livewire::test(CreateRepairJob::class)
        ->fillForm([
            'laptop_id' => $laptop->id,
            'type' => JobType::Repair->value,
            'assignee' => JobAssignee::InHouse->value,
            'cost' => '100',
            'cost_currency_id' => $usd->id,
            'cost_exchange_rate' => '0',
        ])
        ->call('create')
        ->assertHasFormErrors(['cost_exchange_rate']);

    $this->assertDatabaseEmpty('repair_jobs');
});

test('choosing an expense currency prefills the exchange rate from that currency', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $usd = Currency::factory()->create(['exchange_rate' => '83.5']);

    Livewire::test(CreateRepairJob::class)
        ->set('data.cost_currency_id', $usd->id)
        ->assertFormSet(['cost_exchange_rate' => '83.500000']);
});
