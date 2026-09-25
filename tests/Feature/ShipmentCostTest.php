<?php

use App\Models\Currency;
use App\Models\Laptop;
use App\Models\Shipment;
use Illuminate\Database\QueryException;

test('the total cost converts every cost line at its own exchange rate', function () {
    $shipment = shipmentWithMixedCurrencyCosts();

    expect($shipment->fresh()->total_cost)->toBe('116400.50');
});

test('a shipment without costs has a total cost of zero', function () {
    $shipment = Shipment::factory()->create();

    expect($shipment->fresh()->total_cost)->toBe('0.00');
});

test('the average cost divides the total cost across the shipment laptops', function () {
    $shipment = shipmentWithMixedCurrencyCosts();
    Laptop::factory()->count(3)->for($shipment)->create();

    expect($shipment->fresh()->average_cost_per_laptop)->toBe('38800.17');
});

test('the average cost ignores soft-deleted laptops', function () {
    $shipment = shipmentWithMixedCurrencyCosts();
    $laptops = Laptop::factory()->count(3)->for($shipment)->create();
    $laptops->first()->delete();

    expect($shipment->fresh()->average_cost_per_laptop)->toBe('58200.25');
});

test('the average cost is null while the shipment has no laptops', function () {
    $shipment = shipmentWithMixedCurrencyCosts();

    expect($shipment->fresh()->average_cost_per_laptop)->toBeNull();
});

test('a currency used by a shipment cost cannot be deleted', function () {
    $shipment = shipmentWithMixedCurrencyCosts();

    expect(fn () => $shipment->invoiceValueCurrency->delete())->toThrow(QueryException::class);
    $this->assertModelExists(Currency::where('code', 'USD')->firstOrFail());
});
