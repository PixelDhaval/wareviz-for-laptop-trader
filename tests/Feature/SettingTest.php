<?php

use App\Enums\SaleType;
use App\Enums\ShipmentCostType;
use App\Models\Currency;
use App\Models\Setting;

test('current returns the same singleton row every time, creating it on first access', function () {
    expect(Setting::query()->count())->toBe(0);

    $first = Setting::current();
    $second = Setting::current();

    expect(Setting::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id);
});

test('saleCurrencyId resolves the configured currency for each sale type', function () {
    $local = Currency::factory()->create();
    $export = Currency::factory()->create();

    $setting = Setting::factory()->create([
        'sale_local_currency_id' => $local->id,
        'sale_export_currency_id' => $export->id,
    ]);

    expect($setting->saleCurrencyId(SaleType::Local))->toBe($local->id)
        ->and($setting->saleCurrencyId(SaleType::Export))->toBe($export->id);
});

test('shipmentCostCurrencyId resolves the configured currency for each expense type', function () {
    $invoice = Currency::factory()->create();

    $setting = Setting::factory()->create([
        'invoice_value_currency_id' => $invoice->id,
    ]);

    expect($setting->shipmentCostCurrencyId(ShipmentCostType::InvoiceValue))->toBe($invoice->id)
        ->and($setting->shipmentCostCurrencyId(ShipmentCostType::FreightCost))->toBeNull();
});

test('repairCostCurrency resolves the configured default repair expense currency', function () {
    $currency = Currency::factory()->create();

    $setting = Setting::factory()->create(['repair_cost_currency_id' => $currency->id]);

    expect($setting->repair_cost_currency_id)->toBe($currency->id)
        ->and($setting->repairCostCurrency->is($currency))->toBeTrue();
});
