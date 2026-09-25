<?php

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Filament\Resources\RepairJobs\Pages\CreateRepairJob;
use App\Filament\Resources\RepairJobs\Pages\EditRepairJob;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\RepairJob;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('a super_admin can create a repair job', function () {
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

    expect($job->status)->toBe(JobStatus::Pending)
        ->and($job->cost)->toBeNull();
});

test('the expense field is not shown when creating a repair job — it is only asked for on completion', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Livewire::test(CreateRepairJob::class)
        ->assertFormFieldHidden('cost');
});

test('the expense field stays hidden while editing a job that is not yet completed', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $job = RepairJob::factory()->create(['status' => JobStatus::Pending]);

    Livewire::test(EditRepairJob::class, ['record' => $job->getKey()])
        ->assertFormFieldHidden('cost');
});

test('marking a job as completed reveals the expense field and requires it before saving', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $job = RepairJob::factory()->create(['status' => JobStatus::Pending, 'cost' => null]);

    Livewire::test(EditRepairJob::class, ['record' => $job->getKey()])
        ->set('data.status', JobStatus::Completed->value)
        ->assertFormFieldVisible('cost')
        ->call('save')
        ->assertHasFormErrors(['cost', 'cost_currency_id', 'cost_exchange_rate']);

    expect($job->fresh()->status)->toBe(JobStatus::Pending);
});

test('a job can be marked completed once its expense is filled in', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $job = RepairJob::factory()->create(['status' => JobStatus::Pending]);
    $usd = Currency::factory()->create();

    Livewire::test(EditRepairJob::class, ['record' => $job->getKey()])
        ->fillForm([
            'status' => JobStatus::Completed->value,
            'cost' => '150',
            'cost_currency_id' => $usd->id,
            'cost_exchange_rate' => '83.5',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $job->refresh();

    expect($job->status)->toBe(JobStatus::Completed)
        ->and($job->cost)->toBe('150.00')
        ->and($job->cost_currency_id)->toBe($usd->id)
        ->and($job->cost_exchange_rate)->toBe('83.500000');
});

test('the expense currency and exchange rate default from settings once completing a job', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $usd = Currency::factory()->create(['exchange_rate' => '83.5']);
    Setting::factory()->create(['repair_cost_currency_id' => $usd->id]);
    $job = RepairJob::factory()->create(['status' => JobStatus::Pending]);

    Livewire::test(EditRepairJob::class, ['record' => $job->getKey()])
        ->set('data.status', JobStatus::Completed->value)
        ->assertSchemaStateSet(['cost_currency_id' => $usd->id, 'cost_exchange_rate' => '83.500000']);
});

test('choosing an expense currency prefills the exchange rate from that currency', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $job = RepairJob::factory()->create(['status' => JobStatus::Pending]);
    $usd = Currency::factory()->create(['exchange_rate' => '83.5']);

    Livewire::test(EditRepairJob::class, ['record' => $job->getKey()])
        ->set('data.status', JobStatus::Completed->value)
        ->set('data.cost_currency_id', $usd->id)
        ->assertSchemaStateSet(['cost_exchange_rate' => '83.500000']);
});

test('the expense exchange rate is fetched from the historical rate api for the date sent', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD', 'exchange_rate' => '80']);
    $job = RepairJob::factory()->create(['status' => JobStatus::Pending, 'sent_at' => '2026-03-10']);

    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['date' => '2026-03-10', 'usd' => ['inr' => 83.25]]),
    ]);

    Livewire::test(EditRepairJob::class, ['record' => $job->getKey()])
        ->set('data.status', JobStatus::Completed->value)
        ->set('data.cost_currency_id', $usd->id)
        ->assertSchemaStateSet(['cost_exchange_rate' => '83.250000']);
});

test('the expense exchange rate falls back to the currency\'s stored rate when the api has nothing for that date', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD', 'exchange_rate' => '80']);
    $job = RepairJob::factory()->create(['status' => JobStatus::Pending, 'sent_at' => '2026-03-10']);

    Http::fake(['*' => Http::response(null, 500)]);

    Livewire::test(EditRepairJob::class, ['record' => $job->getKey()])
        ->set('data.status', JobStatus::Completed->value)
        ->set('data.cost_currency_id', $usd->id)
        ->assertSchemaStateSet(['cost_exchange_rate' => '80.000000']);
});

test('the fetch exchange rate button refreshes the exchange rate for the currency and date sent', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);
    $job = RepairJob::factory()->create(['status' => JobStatus::Pending, 'sent_at' => '2026-03-10']);

    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['date' => '2026-03-10', 'usd' => ['inr' => 83.25]]),
    ]);

    Livewire::test(EditRepairJob::class, ['record' => $job->getKey()])
        ->set('data.status', JobStatus::Completed->value)
        ->set('data.cost_currency_id', $usd->id)
        // Simulate a stale rate (manually overwritten, or fetched before the
        // API had this date's data) — isolates the button's own effect from
        // the auto-fetch above.
        ->set('data.cost_exchange_rate', '999.000000')
        ->callAction(TestAction::make('fetchExchangeRate')->schemaComponent('sent_at'))
        ->assertSchemaStateSet(['cost_exchange_rate' => '83.250000']);
});
