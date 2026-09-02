<?php

use App\Enums\JobAssignee;
use App\Enums\JobType;
use App\Enums\LaptopStatus;
use App\Filament\Pages\ScanLookup;
use App\Models\Agency;
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

test('sending a laptop to an agency via the scan lookup action creates the job', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $agency = Agency::factory()->create();

    Livewire::test(ScanLookup::class)
        ->set('code', $laptop->asset_code)
        ->call('lookup')
        ->mountAction('sendForJob')
        ->fillForm(['type' => JobType::Repaint->value, 'assignee' => JobAssignee::Agency->value])
        ->fillForm(['agency_id' => $agency->id, 'cost' => 50])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $job = RepairJob::query()->where('laptop_id', $laptop->id)->sole();

    expect($job->assignee)->toBe(JobAssignee::Agency)
        ->and($job->agency_id)->toBe($agency->id)
        ->and($laptop->fresh()->status)->toBe(LaptopStatus::InRepair);
});
