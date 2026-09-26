<?php

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\LaptopStatus;
use App\Filament\Pages\ScanLookup;
use App\Models\Agency;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\RepairJob;
use App\Models\User;
use Livewire\Livewire;

test('sending a laptop to an agency via the scan lookup action requires an agency', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    Livewire::test(ScanLookup::class)
        ->set('code', $laptop->asset_code)
        ->call('lookup')
        ->mountAction('sendForJob')
        ->fillForm(['type' => JobType::Repair->value, 'assignee' => JobAssignee::Agency->value])
        ->assertFormFieldVisible('agency_id')
        ->fillForm(['agency_id' => null])
        ->callMountedAction()
        ->assertHasFormErrors(['agency_id']);

    expect(RepairJob::query()->count())->toBe(0);
});

test('sending a laptop to an agency via the scan lookup action creates the job without asking for an expense', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $agency = Agency::factory()->create();

    Livewire::test(ScanLookup::class)
        ->set('code', $laptop->asset_code)
        ->call('lookup')
        ->mountAction('sendForJob')
        ->fillForm(['type' => JobType::Repaint->value, 'assignee' => JobAssignee::Agency->value])
        ->fillForm(['agency_id' => $agency->id])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $job = RepairJob::query()->where('laptop_id', $laptop->id)->sole();

    expect($job->assignee)->toBe(JobAssignee::Agency)
        ->and($job->agency_id)->toBe($agency->id)
        ->and($job->cost)->toBeNull()
        ->and($laptop->fresh()->status)->toBe(LaptopStatus::InRepair);
});

test('completing a job via the scan lookup action requires and saves its expense', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InRepair]);
    $job = RepairJob::factory()->for($laptop)->create(['status' => JobStatus::Pending, 'cost' => null]);
    $currency = Currency::factory()->create();

    Livewire::test(ScanLookup::class)
        ->set('code', $laptop->asset_code)
        ->call('lookup')
        ->mountAction('completeJob')
        ->callMountedAction()
        ->assertHasFormErrors(['cost', 'cost_currency_id', 'cost_exchange_rate'])
        ->fillForm([
            'cost' => 50,
            'cost_currency_id' => $currency->id,
            'cost_exchange_rate' => 83.5,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($job->fresh()->status)->toBe(JobStatus::Completed)
        ->and($job->fresh()->cost_currency_id)->toBe($currency->id)
        ->and($job->fresh()->cost_exchange_rate)->toBe('83.500000')
        ->and($laptop->fresh()->status)->toBe(LaptopStatus::InStock);
});
