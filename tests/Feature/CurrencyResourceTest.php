<?php

use App\Filament\Resources\Currencies\Pages\CreateCurrency;
use App\Models\Currency;
use App\Models\User;
use Livewire\Livewire;

test('a super_admin can create a currency', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Livewire::test(CreateCurrency::class)
        ->fillForm([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => '83.5',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $currency = Currency::where('code', 'USD')->firstOrFail();

    expect($currency->name)->toBe('US Dollar')
        ->and($currency->exchange_rate)->toBe('83.500000')
        ->and($currency->is_base)->toBeFalse();
});

test('a currency code must be three uppercase letters', function (string $code) {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Livewire::test(CreateCurrency::class)
        ->fillForm(['code' => $code, 'name' => 'Whatever'])
        ->call('create')
        ->assertHasFormErrors(['code']);

    $this->assertDatabaseEmpty('currencies');
})->with([
    'lowercase' => 'usd',
    'too short' => 'US',
    'too long' => 'USDX',
    'digits' => 'U5D',
]);

test('marking a currency as base clears the flag from the previous base currency', function () {
    $previous = Currency::factory()->base()->create();

    $next = Currency::factory()->base()->create();

    expect($previous->fresh()->is_base)->toBeFalse()
        ->and($next->fresh()->is_base)->toBeTrue()
        ->and(Currency::where('is_base', true)->count())->toBe(1);
});

test('the base currency always has an exchange rate of 1', function () {
    $currency = Currency::factory()->create(['is_base' => true, 'exchange_rate' => 5]);

    expect($currency->fresh()->exchange_rate)->toBe('1.000000');
});
