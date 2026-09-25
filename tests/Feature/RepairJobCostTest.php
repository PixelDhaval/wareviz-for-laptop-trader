<?php

use App\Models\Currency;
use App\Models\Laptop;
use App\Models\RepairJob;
use Illuminate\Database\QueryException;

test('a repair job expense converts to the base currency at its own exchange rate', function () {
    $usd = Currency::factory()->create();

    $job = RepairJob::factory()
        ->for(Laptop::factory())
        ->withCost('100.00', $usd, '83.5')
        ->create();

    expect($job->fresh()->cost_in_base_currency)->toBe('8350.00000000');
});

test('a repair job with no expense converts to zero', function () {
    $job = RepairJob::factory()->for(Laptop::factory())->create(['cost' => null]);

    expect($job->fresh()->cost_in_base_currency)->toBe('0.00000000');
});

test('a currency used by a repair job cannot be deleted', function () {
    $usd = Currency::factory()->create();
    RepairJob::factory()->for(Laptop::factory())->withCost('50.00', $usd, '83.5')->create();

    expect(fn () => $usd->delete())->toThrow(QueryException::class);
    $this->assertModelExists(Currency::findOrFail($usd->id));
});
