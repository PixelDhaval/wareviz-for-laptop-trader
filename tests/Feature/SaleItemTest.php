<?php

use App\Enums\LaptopStatus;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\QueryException;

test('adding a laptop to a sale marks it as sold', function () {
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    SaleItem::factory()->for($laptop)->create();

    expect($laptop->fresh()->status)->toBe(LaptopStatus::Sold);
});

test('removing a laptop from a sale returns it to stock', function () {
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $item = SaleItem::factory()->for($laptop)->create();

    $item->delete();

    expect($laptop->fresh()->status)->toBe(LaptopStatus::InStock);
});

test('removing a laptop from a sale does not touch a status set by something else', function () {
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $item = SaleItem::factory()->for($laptop)->create();

    $laptop->update(['status' => LaptopStatus::Defective]);
    $item->delete();

    expect($laptop->fresh()->status)->toBe(LaptopStatus::Defective);
});

test('deleting a sale returns every one of its laptops to stock', function () {
    $sale = Sale::factory()->create();
    $laptops = Laptop::factory()->count(2)->create(['status' => LaptopStatus::InStock]);
    $laptops->each(fn (Laptop $laptop) => SaleItem::factory()->for($sale)->for($laptop)->create());

    $sale->delete();

    expect($laptops->fresh()->pluck('status')->unique()->all())->toBe([LaptopStatus::InStock]);
    $this->assertDatabaseCount('sale_items', 0);
});

test('a sale item price converts to the base currency at its own exchange rate', function () {
    $usd = Currency::factory()->create();

    $item = SaleItem::factory()->withPrice('200.00', $usd, '83.5')->create();

    expect($item->fresh()->price_in_base_currency)->toBe('16700.00000000');
});

test('a sale total value sums every item, converted to the base currency', function () {
    $sale = Sale::factory()->create();
    $usd = Currency::factory()->create();
    $eur = Currency::factory()->create();

    SaleItem::factory()->for($sale)->withPrice('100.00', $usd, '83.5')->create();
    SaleItem::factory()->for($sale)->withPrice('50.00', $eur, '90')->create();

    expect($sale->fresh()->total_sale_value)->toBe('12850.00');
});

test('a sale with no items has a total value of zero', function () {
    $sale = Sale::factory()->create();

    expect($sale->fresh()->total_sale_value)->toBe('0.00');
});

test('a currency used by a sale item cannot be deleted', function () {
    $usd = Currency::factory()->create();
    SaleItem::factory()->withPrice('100.00', $usd, '83.5')->create();

    expect(fn () => $usd->delete())->toThrow(QueryException::class);
});

test('the same laptop cannot be added to the same sale twice', function () {
    $sale = Sale::factory()->create();
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    SaleItem::factory()->for($sale)->for($laptop)->create();

    expect(fn () => SaleItem::factory()->for($sale)->for($laptop)->create())
        ->toThrow(QueryException::class);
});
