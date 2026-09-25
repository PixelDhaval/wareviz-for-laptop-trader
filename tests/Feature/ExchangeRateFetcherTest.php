<?php

use App\Models\Currency;
use App\Support\ExchangeRateFetcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

test('the base currency always returns a rate of 1 without calling the api', function () {
    $base = Currency::factory()->base()->create();

    Http::fake();

    $rate = ExchangeRateFetcher::rate($base, Carbon::parse('2026-01-01'));

    expect($rate)->toBe('1.000000');
    Http::assertNothingSent();
});

test('it returns null when no base currency is configured', function () {
    $usd = Currency::factory()->create(['code' => 'USD']);

    Http::fake();

    expect(ExchangeRateFetcher::rate($usd, Carbon::parse('2026-01-01')))->toBeNull();
    Http::assertNothingSent();
});

test('it returns null for a future date without calling the api', function () {
    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);

    Http::fake();

    expect(ExchangeRateFetcher::rate($usd, now()->addDay()))->toBeNull();
    Http::assertNothingSent();
});

test('it fetches the rate from the jsdelivr endpoint', function () {
    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);

    Http::preventStrayRequests();
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['date' => '2026-01-01', 'usd' => ['inr' => 83.123456789]]),
    ]);

    $rate = ExchangeRateFetcher::rate($usd, Carbon::parse('2026-01-01'));

    expect($rate)->toBe('83.123457');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '2026-01-01/v1/currencies/usd.json'));
});

test('it falls back to the cloudflare endpoint when jsdelivr fails', function () {
    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);

    Http::preventStrayRequests();
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(null, 500),
        '*.currency-api.pages.dev/*' => Http::response(['date' => '2026-01-01', 'usd' => ['inr' => 84]]),
    ]);

    $rate = ExchangeRateFetcher::rate($usd, Carbon::parse('2026-01-01'));

    expect($rate)->toBe('84.000000');
    Http::assertSentCount(2);
});

test('an unfaked request never reaches the real network and degrades to null', function () {
    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);

    // No Http::fake() at all here — Pest.php's global preventStrayRequests()
    // means any real request throws instead of hitting the internet, and
    // the fetcher's own try/catch must swallow that and degrade gracefully.
    expect(ExchangeRateFetcher::rate($usd, Carbon::parse('2026-01-01')))->toBeNull();
});

test('it returns null when both endpoints fail', function () {
    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);

    Http::preventStrayRequests();
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(null, 500),
        '*.currency-api.pages.dev/*' => Http::response(null, 500),
    ]);

    expect(ExchangeRateFetcher::rate($usd, Carbon::parse('2026-01-01')))->toBeNull();
});

test('it returns null when the api response has no rate for the requested pair', function () {
    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);

    Http::preventStrayRequests();
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['date' => '2026-01-01', 'usd' => ['eur' => 0.9]]),
        '*.currency-api.pages.dev/*' => Http::response(['date' => '2026-01-01', 'usd' => ['eur' => 0.9]]),
    ]);

    expect(ExchangeRateFetcher::rate($usd, Carbon::parse('2026-01-01')))->toBeNull();
});

test('it caches the rate so a repeated call does not hit the api again', function () {
    Currency::factory()->base()->create(['code' => 'INR']);
    $usd = Currency::factory()->create(['code' => 'USD']);

    Http::preventStrayRequests();
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['date' => '2026-01-01', 'usd' => ['inr' => 83]]),
    ]);

    ExchangeRateFetcher::rate($usd, Carbon::parse('2026-01-01'));
    ExchangeRateFetcher::rate($usd, Carbon::parse('2026-01-01'));

    Http::assertSentCount(1);
});
