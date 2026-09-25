<?php

use App\Enums\SaleType;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Models\Buyer;
use App\Models\Currency;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('a super_admin can create a local sale', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create();
    $buyer = Buyer::factory()->create(['name' => 'Acme Traders']);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'type' => SaleType::Local->value,
            'buyer_id' => $buyer->id,
            'currency_id' => $currency->id,
            'exchange_rate' => '1',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::sole();

    expect($sale->type)->toBe(SaleType::Local)
        ->and($sale->buyer_id)->toBe($buyer->id)
        ->and($sale->destination_country)->toBeNull();
});

test('a sale is assigned a code automatically and cannot have one submitted manually', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create();

    Livewire::test(CreateSale::class)
        ->fillForm([
            'type' => SaleType::Local->value,
            'currency_id' => $currency->id,
            'exchange_rate' => '1',
        ])
        ->set('data.code', 'SOMETHING-MANUAL')
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::sole();

    expect($sale->code)->not->toBe('SOMETHING-MANUAL')
        ->and($sale->code)->not->toBeEmpty();
});

test('a sale can be created without a buyer', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create();

    Livewire::test(CreateSale::class)
        ->fillForm([
            'type' => SaleType::Local->value,
            'currency_id' => $currency->id,
            'exchange_rate' => '1',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Sale::sole()->buyer_id)->toBeNull();
});

test('an export sale requires a destination country and a reference number', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create();

    Livewire::test(CreateSale::class)
        ->fillForm([
            'type' => SaleType::Export->value,
            'currency_id' => $currency->id,
            'exchange_rate' => '1',
        ])
        ->call('create')
        ->assertHasFormErrors(['destination_country' => 'required', 'reference_no' => 'required']);

    $this->assertDatabaseCount('sales', 0);
});

test('an export sale can be created with its destination and reference number', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create();

    Livewire::test(CreateSale::class)
        ->fillForm([
            'type' => SaleType::Export->value,
            'destination_country' => 'United Arab Emirates',
            'reference_no' => 'INV-0001',
            'currency_id' => $currency->id,
            'exchange_rate' => '3.67',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::sole();

    expect($sale->destination_country)->toBe('United Arab Emirates')
        ->and($sale->reference_no)->toBe('INV-0001')
        ->and($sale->exchange_rate)->toBe('3.670000');
});

test('a local sale does not require a destination country or reference number', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create();

    Livewire::test(CreateSale::class)
        ->fillForm([
            'type' => SaleType::Local->value,
            'currency_id' => $currency->id,
            'exchange_rate' => '1',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseCount('sales', 1);
});

test('editing a sale shows its generated code as read-only and does not regenerate it', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create(['type' => SaleType::Local]);
    $originalCode = $sale->code;

    Livewire::test(EditSale::class, ['record' => $sale->getKey()])
        ->assertFormSet(['code' => $originalCode])
        ->assertFormFieldIsDisabled('code')
        ->fillForm(['notes' => 'Updated notes'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($sale->fresh()->code)->toBe($originalCode);
});

test('choosing a sale currency prefills the exchange rate from that currency', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create(['exchange_rate' => '83.5']);

    Livewire::test(CreateSale::class)
        ->set('data.currency_id', $currency->id)
        ->assertFormSet(['exchange_rate' => '83.500000']);
});

test('the sale exchange rate is fetched from the historical rate api for the sale date', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD', 'exchange_rate' => '80']);

    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['date' => '2026-03-10', 'usd' => ['inr' => 83.25]]),
    ]);

    Livewire::test(CreateSale::class)
        ->set('data.sold_at', '2026-03-10')
        ->set('data.currency_id', $usd->id)
        ->assertFormSet(['exchange_rate' => '83.250000']);
});

test('the fetch exchange rate button refreshes the exchange rate for the currency and sale date', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);

    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['date' => '2026-03-10', 'usd' => ['inr' => 83.25]]),
    ]);

    Livewire::test(CreateSale::class)
        ->set('data.sold_at', '2026-03-10')
        ->set('data.currency_id', $usd->id)
        // Simulate a stale rate (manually overwritten, or fetched before the
        // API had this date's data) — isolates the button's own effect from
        // the auto-fetch above.
        ->set('data.exchange_rate', '999.000000')
        ->callAction(TestAction::make('fetchExchangeRate')->schemaComponent('sold_at'))
        ->assertFormSet(['exchange_rate' => '83.250000']);
});

test('the sale exchange rate falls back to the currency\'s stored rate when the api has nothing for that date', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD', 'exchange_rate' => '80']);

    Http::fake(['*' => Http::response(null, 500)]);

    Livewire::test(CreateSale::class)
        ->set('data.sold_at', '2026-03-10')
        ->set('data.currency_id', $usd->id)
        ->assertFormSet(['exchange_rate' => '80.000000']);
});

test('the sale currency and exchange rate default from settings for the selected sale type', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $local = Currency::factory()->create(['exchange_rate' => '1']);
    $export = Currency::factory()->create(['exchange_rate' => '3.67']);

    Setting::factory()->create([
        'sale_local_currency_id' => $local->id,
        'sale_export_currency_id' => $export->id,
    ]);

    Livewire::test(CreateSale::class)
        ->assertFormSet(['currency_id' => $local->id, 'exchange_rate' => '1.000000'])
        ->set('data.type', SaleType::Export->value)
        ->assertFormSet(['currency_id' => $export->id, 'exchange_rate' => '3.670000']);
});
