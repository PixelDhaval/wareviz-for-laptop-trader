<?php

use App\Models\Currency;
use App\Models\Laptop;
use App\Models\RepairJob;
use App\Models\Shipment;

test('the purchase cost is the laptop\'s share of its shipment average cost per laptop', function () {
    $shipment = shipmentWithMixedCurrencyCosts();
    Laptop::factory()->count(2)->for($shipment)->create();
    $laptop = Laptop::factory()->for($shipment)->create();

    expect($laptop->fresh()->purchase_cost)->toBe($shipment->fresh()->average_cost_per_laptop);
});

test('the purchase cost is zero when the shipment has no configured costs', function () {
    $shipment = Shipment::factory()->create();
    $laptop = Laptop::factory()->for($shipment)->create();

    expect($laptop->fresh()->purchase_cost)->toBe('0.00');
});

test('the repair expense total sums every job on the laptop, converted to the base currency', function () {
    $usd = Currency::factory()->create();
    $eur = Currency::factory()->create();
    $laptop = Laptop::factory()->create();

    RepairJob::factory()->for($laptop)->withCost('100.00', $usd, '83.5')->create();
    RepairJob::factory()->for($laptop)->withCost('20.00', $eur, '90')->create();

    expect($laptop->fresh()->repair_expense_total)->toBe('10150.00');
});

test('the repair expense total is zero when the laptop has no repair jobs', function () {
    $laptop = Laptop::factory()->create();

    expect($laptop->fresh()->repair_expense_total)->toBe('0.00');
});

test('the total cost is the purchase cost plus the repair expense total', function () {
    $shipment = shipmentWithMixedCurrencyCosts();
    $laptop = Laptop::factory()->for($shipment)->create();
    $usd = Currency::factory()->create();
    RepairJob::factory()->for($laptop)->withCost('100.00', $usd, '83.5')->create();

    expect($laptop->fresh()->total_cost)->toBe('124750.50');
});

test('the purchase cost and total cost are null once the laptop is soft-deleted and no other laptop is left on its shipment', function () {
    $shipment = shipmentWithMixedCurrencyCosts();
    $laptop = Laptop::factory()->for($shipment)->create();

    $laptop->delete();

    $trashedLaptop = Laptop::withTrashed()->with('shipment')->findOrFail($laptop->id);

    expect($trashedLaptop->purchase_cost)->toBeNull()
        ->and($trashedLaptop->total_cost)->toBeNull();
});
