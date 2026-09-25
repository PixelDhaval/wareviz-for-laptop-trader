<?php

use App\Enums\LaptopStatus;
use App\Filament\Resources\Sales\Pages\ScanSale;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Livewire\Livewire;

test('scanning a laptop opens a confirmation modal with its details', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->set('code', $laptop->asset_code)
        ->call('scan')
        ->assertSet('code', null)
        ->assertMountedActionModalSee($laptop->asset_code)
        ->assertMountedActionModalSee($laptop->brand->name)
        ->assertMountedActionModalSee($laptop->processor->name);

    $this->assertDatabaseCount('sale_items', 0);
});

test('confirming the price in the modal adds the laptop to the sale and marks it reserved while the sale is a draft', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create(['is_completed' => false]);
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->set('code', $laptop->asset_code)
        ->call('scan')
        ->fillForm(['price' => '199.99'])->callMountedAction()
        ->assertSet('message', "{$laptop->asset_code} added to the sale.")
        ->assertSet('messageIsError', false);

    $item = SaleItem::query()->where('sale_id', $sale->id)->where('laptop_id', $laptop->id)->sole();

    expect($item->price)->toBe('199.99')
        ->and($item->price_currency_id)->toBe($sale->currency_id)
        ->and($item->price_exchange_rate)->toBe($sale->exchange_rate)
        ->and($laptop->fresh()->status)->toBe(LaptopStatus::Reserved);
});

test('scanning an unknown code shows an error and opens no modal', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->set('code', 'WV999999')
        ->call('scan')
        ->assertSet('messageIsError', true)
        ->assertActionNotMounted('confirmScan');

    $this->assertDatabaseCount('sale_items', 0);
});

test('scanning a laptop that is not in stock shows an error and opens no modal', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::Defective]);

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->set('code', $laptop->asset_code)
        ->call('scan')
        ->assertSet('messageIsError', true)
        ->assertActionNotMounted('confirmScan');

    $this->assertDatabaseCount('sale_items', 0);
    expect($laptop->fresh()->status)->toBe(LaptopStatus::Defective);
});

test('scanning an already-added laptop again shows an error and opens no modal', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    $component = Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->set('code', $laptop->asset_code)
        ->call('scan')
        ->fillForm(['price' => '100'])->callMountedAction();

    $component
        ->set('code', $laptop->asset_code)
        ->call('scan')
        ->assertSet('messageIsError', true)
        ->assertSet('message', "{$laptop->asset_code} is already in this sale.")
        ->assertActionNotMounted('confirmScan');

    expect(SaleItem::query()->where('sale_id', $sale->id)->count())->toBe(1);
});

test('setting a price updates the sale item', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $item = SaleItem::factory()->for($sale)->for($laptop)->create(['price' => 0]);

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->callAction('setPrice', data: ['price' => '199.99'], arguments: ['item' => $item->id]);

    expect($item->fresh()->price)->toBe('199.99');
});

test('removing a scanned item deletes it and returns the laptop to stock', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $item = SaleItem::factory()->for($sale)->for($laptop)->create();
    $laptop->update(['status' => LaptopStatus::Sold]);

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->callAction('removeItem', arguments: ['item' => $item->id]);

    $this->assertModelMissing($item);
    expect($laptop->fresh()->status)->toBe(LaptopStatus::InStock);
});

test('the total bill sums every scanned item\'s price, converted into the sale\'s own currency', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $eur = Currency::factory()->create();
    $usd = Currency::factory()->create();
    $sale = Sale::factory()->create(['currency_id' => $eur->id, 'exchange_rate' => '90']);
    $laptopA = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $laptopB = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    // Items priced in USD @ 83.5: base total is 12525.00. Converted into the
    // sale's own currency (EUR @ 90 to base): 12525.00 / 90 = 139.17.
    SaleItem::factory()->for($sale)->for($laptopA)->withPrice('100.00', $usd, '83.5')->create();
    SaleItem::factory()->for($sale)->for($laptopB)->withPrice('50.00', $usd, '83.5')->create();

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->assertSee('139.17')
        ->assertSee($eur->code)
        ->assertDontSee('12,525.00');
});

test('completing the sale is disabled when it has no laptops', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->assertActionDisabled('completeSale');

    expect($sale->fresh()->is_completed)->toBeFalse();
});

test('completing the sale marks it completed and moves on to the sale page', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    SaleItem::factory()->for($sale)->for($laptop)->create();

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->assertActionEnabled('completeSale')
        ->callAction('completeSale')
        ->assertRedirect(SaleResource::getUrl('view', ['record' => $sale]));

    expect($sale->fresh()->is_completed)->toBeTrue();
});

test('saving as draft leaves the sale incomplete and moves on to the sale page', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->callAction('saveAsDraft')
        ->assertRedirect(SaleResource::getUrl('view', ['record' => $sale]));

    expect($sale->fresh()->is_completed)->toBeFalse();
});

test('a user without update permission on sales cannot open the scan page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();

    Livewire::test(ScanSale::class, ['record' => $sale->getKey()])
        ->assertForbidden();
});
