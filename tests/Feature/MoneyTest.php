<?php

use App\Models\Currency;
use App\Support\Money;

test('currencySymbol returns the symbol when set', function () {
    $currency = Currency::factory()->create(['code' => 'USD', 'symbol' => '$']);

    expect(Money::currencySymbol($currency))->toBe('$');
});

test('currencySymbol falls back to the code when the symbol is unset', function () {
    $currency = Currency::factory()->create(['code' => 'AED', 'symbol' => null]);

    expect(Money::currencySymbol($currency))->toBe('AED');
});

test('currencySymbol is empty for no currency', function () {
    expect(Money::currencySymbol(null))->toBe('');
});

test('currencyPrefix appends a trailing space to the symbol', function () {
    $currency = Currency::factory()->create(['code' => 'USD', 'symbol' => '$']);

    expect(Money::currencyPrefix($currency))->toBe('$ ');
});

test('currencyPrefix is empty for no currency, not a stray space', function () {
    expect(Money::currencyPrefix(null))->toBe('');
});
