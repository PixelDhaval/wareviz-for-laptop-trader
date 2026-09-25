<?php

use App\Filament\Resources\Shipments\Pages\CreateShipment;
use App\Filament\Resources\Shipments\Pages\EditShipment;
use App\Filament\Resources\Shipments\Pages\ListShipments;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('a super_admin can save a shipment with costs in different currencies', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $usd = Currency::factory()->create();
    $eur = Currency::factory()->create();
    $supplier = Supplier::factory()->create();

    Livewire::test(CreateShipment::class)
        ->fillForm([
            'code' => 'SHP-1',
            'supplier_id' => $supplier->id,
            'invoice_value' => '1000',
            'invoice_value_currency_id' => $usd->id,
            'invoice_value_exchange_rate' => '83.5',
            'freight_cost' => '200',
            'freight_cost_currency_id' => $eur->id,
            'freight_cost_exchange_rate' => '90',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $shipment = Shipment::where('code', 'SHP-1')->firstOrFail();

    expect($shipment->invoice_value)->toBe('1000.00')
        ->and($shipment->invoice_value_currency_id)->toBe($usd->id)
        ->and($shipment->invoice_value_exchange_rate)->toBe('83.500000')
        ->and($shipment->freight_cost)->toBe('200.00')
        ->and($shipment->freight_cost_currency_id)->toBe($eur->id)
        ->and($shipment->freight_cost_exchange_rate)->toBe('90.000000')
        ->and($shipment->total_cost)->toBe('101500.00');
});

test('a shipment requires a supplier', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Livewire::test(CreateShipment::class)
        ->fillForm(['code' => 'SHP-NO-SUPPLIER'])
        ->call('create')
        ->assertHasFormErrors(['supplier_id' => 'required']);

    $this->assertDatabaseMissing('shipments', ['code' => 'SHP-NO-SUPPLIER']);
});

test('a shipment can be saved without any costs', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $supplier = Supplier::factory()->create();

    Livewire::test(CreateShipment::class)
        ->fillForm(['code' => 'SHP-2', 'supplier_id' => $supplier->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $shipment = Shipment::where('code', 'SHP-2')->firstOrFail();

    expect($shipment->total_cost)->toBe('0.00')
        ->and($shipment->duty_currency_id)->toBeNull()
        ->and($shipment->duty_exchange_rate)->toBe('1.000000');
});

test('a currency and exchange rate are required once an amount is entered', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $supplier = Supplier::factory()->create();

    Livewire::test(CreateShipment::class)
        ->fillForm([
            'code' => 'SHP-3',
            'supplier_id' => $supplier->id,
            'duty' => '250',
            'duty_exchange_rate' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'duty_currency_id' => 'required',
            'duty_exchange_rate' => 'required',
        ]);

    $this->assertDatabaseMissing('shipments', ['code' => 'SHP-3']);
});

test('a cost cannot be negative or use a zero exchange rate', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $usd = Currency::factory()->create();
    $supplier = Supplier::factory()->create();

    Livewire::test(CreateShipment::class)
        ->fillForm([
            'code' => 'SHP-4',
            'supplier_id' => $supplier->id,
            'duty' => '-5',
            'other_expense' => '10',
            'other_expense_currency_id' => $usd->id,
            'other_expense_exchange_rate' => '0',
        ])
        ->call('create')
        ->assertHasFormErrors(['duty' => 'min', 'other_expense_exchange_rate']);

    $this->assertDatabaseMissing('shipments', ['code' => 'SHP-4']);
});

test('choosing a currency prefills the exchange rate from that currency', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $eur = Currency::factory()->create(['exchange_rate' => '90']);
    $baseless = Currency::factory()->create(['exchange_rate' => null]);

    Livewire::test(CreateShipment::class)
        ->set('data.freight_cost_currency_id', $eur->id)
        ->assertFormSet(['freight_cost_exchange_rate' => '90.000000'])
        ->set('data.freight_cost_currency_id', $baseless->id)
        ->assertFormSet(['freight_cost_exchange_rate' => null]);
});

test('a cost line currency and exchange rate default from settings', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $usd = Currency::factory()->create(['exchange_rate' => '83.5']);

    Setting::factory()->create(['invoice_value_currency_id' => $usd->id]);

    Livewire::test(CreateShipment::class)
        ->assertFormSet([
            'invoice_value_currency_id' => $usd->id,
            'invoice_value_exchange_rate' => '83.500000',
            'freight_cost_currency_id' => null,
        ]);
});

test('a cost line exchange rate is fetched from the historical rate api for the invoice date', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD', 'exchange_rate' => '80']);

    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['date' => '2026-02-01', 'usd' => ['inr' => 82.75]]),
    ]);

    Livewire::test(CreateShipment::class)
        ->set('data.invoice_date', '2026-02-01')
        ->set('data.invoice_value_currency_id', $usd->id)
        ->assertFormSet(['invoice_value_exchange_rate' => '82.750000']);
});

test('changing the invoice date refreshes every already-selected cost line\'s exchange rate', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);

    Http::fake([
        '*2026-02-01*' => Http::response(['date' => '2026-02-01', 'usd' => ['inr' => 82]]),
        '*2026-03-01*' => Http::response(['date' => '2026-03-01', 'usd' => ['inr' => 85]]),
    ]);

    Livewire::test(CreateShipment::class)
        ->set('data.invoice_value_currency_id', $usd->id)
        ->set('data.invoice_date', '2026-02-01')
        ->assertFormSet(['invoice_value_exchange_rate' => '82.000000'])
        ->set('data.invoice_date', '2026-03-01')
        ->assertFormSet(['invoice_value_exchange_rate' => '85.000000']);
});

test('the fetch exchange rate button refreshes every already-selected cost line\'s exchange rate', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);
    $eur = Currency::factory()->create(['code' => 'EUR']);

    Http::fake([
        '*/usd.json' => Http::response(['date' => '2026-02-01', 'usd' => ['inr' => 82]]),
        '*/eur.json' => Http::response(['date' => '2026-02-01', 'eur' => ['inr' => 90]]),
    ]);

    Livewire::test(CreateShipment::class)
        ->set('data.invoice_date', '2026-02-01')
        ->set('data.invoice_value_currency_id', $usd->id)
        ->set('data.freight_cost_currency_id', $eur->id)
        // Simulate stale rates (manually overwritten, or fetched before the
        // API had this date's data) that no longer match the invoice date —
        // isolates the button's own effect from the auto-fetch above.
        ->set('data.invoice_value_exchange_rate', '999.000000')
        ->set('data.freight_cost_exchange_rate', '999.000000')
        ->callAction(TestAction::make('fetchExchangeRates')->schemaComponent('invoice_date'))
        ->assertFormSet([
            'invoice_value_exchange_rate' => '82.000000',
            'freight_cost_exchange_rate' => '90.000000',
        ]);
});

test('a cost line exchange rate falls back to the currency\'s stored rate when the api has nothing for the invoice date', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD', 'exchange_rate' => '80']);

    Http::fake(['*' => Http::response(null, 500)]);

    Livewire::test(CreateShipment::class)
        ->set('data.invoice_date', '2026-02-01')
        ->set('data.invoice_value_currency_id', $usd->id)
        ->assertFormSet(['invoice_value_exchange_rate' => '80.000000']);
});

test('the edit page shows the total cost and the average cost per laptop', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $shipment = shipmentWithMixedCurrencyCosts();
    Laptop::factory()->count(3)->for($shipment)->create();

    Livewire::test(EditShipment::class, ['record' => $shipment->getKey()])
        ->assertSee('116,400.50')
        ->assertSee('38,800.17');
});

test('the shipments list shows the total cost and the average cost per laptop', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $shipment = shipmentWithMixedCurrencyCosts();
    Laptop::factory()->count(3)->for($shipment)->create();

    Livewire::test(ListShipments::class)
        ->assertTableColumnStateSet('total_cost', '116400.50', record: $shipment)
        ->assertTableColumnStateSet('average_cost_per_laptop', '38800.17', record: $shipment);
});
