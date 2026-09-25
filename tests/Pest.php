<?php

use App\Enums\ShipmentCostType;
use App\Models\Currency;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // App\Support\ExchangeRateFetcher makes real outbound HTTP calls.
        // Failing stray requests here means a test that reaches it must
        // explicitly Http::fake() its own response, instead of silently
        // hitting the real network (slow, flaky, and against project
        // convention — see the isolation testing rule).
        Http::preventStrayRequests();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A shipment with a cost in three currencies, INR being the base. Converted at
 * each line's own rate the total is 116,400.50 (83,500 + 18,000 + 5,000 +
 * 1,500.50 + 8,400).
 */
function shipmentWithMixedCurrencyCosts(): Shipment
{
    $usd = Currency::factory()->create(['code' => 'USD']);
    $eur = Currency::factory()->create(['code' => 'EUR']);
    $inr = Currency::factory()->base()->create(['code' => 'INR']);

    return Shipment::factory()
        ->withCost(ShipmentCostType::InvoiceValue, $usd, '1000.00', '83.5')
        ->withCost(ShipmentCostType::FreightCost, $eur, '200.00', '90')
        ->withCost(ShipmentCostType::LocalExpense, $inr, '5000.00', '1')
        ->withCost(ShipmentCostType::Duty, $inr, '1500.50', '1')
        ->withCost(ShipmentCostType::OtherExpense, $usd, '100.00', '84')
        ->create();
}
