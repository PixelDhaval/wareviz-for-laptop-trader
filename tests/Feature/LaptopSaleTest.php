<?php

use App\Models\Currency;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shipment;

test('the sale price and profit are null until the laptop is sold', function () {
    $laptop = Laptop::factory()->create();

    expect($laptop->fresh()->sale_price)->toBeNull()
        ->and($laptop->fresh()->profit)->toBeNull();
});

test('the profit is the sale price minus the total cost', function () {
    $shipment = shipmentWithMixedCurrencyCosts();
    $laptop = Laptop::factory()->for($shipment)->create();
    $usd = Currency::factory()->create();

    SaleItem::factory()->for($laptop)->withPrice('2000.00', $usd, '83.5')->create();

    // total_cost is the shipment's whole 116400.50 (this is its only laptop),
    // sale price is 2000 USD * 83.5 = 167000.00, so profit is the difference.
    expect($laptop->fresh()->total_cost)->toBe('116400.50')
        ->and($laptop->fresh()->sale_price)->toBe('167000.00000000')
        ->and($laptop->fresh()->profit)->toBe('50599.50');
});

test('the profit is null when the purchase cost cannot be determined', function () {
    $shipment = Shipment::factory()->create();
    $laptop = Laptop::factory()->for($shipment)->create();
    $laptop->delete();

    $trashedLaptop = Laptop::withTrashed()->with('shipment')->findOrFail($laptop->id);
    $usd = Currency::factory()->create();
    SaleItem::factory()->for($trashedLaptop)->withPrice('100.00', $usd, '83.5')->create();

    expect($trashedLaptop->fresh()->total_cost)->toBeNull()
        ->and($trashedLaptop->fresh()->profit)->toBeNull();
});
