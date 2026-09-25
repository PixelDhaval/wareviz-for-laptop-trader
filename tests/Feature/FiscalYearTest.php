<?php

use App\Support\FiscalYear;
use Illuminate\Support\Carbon;

test('a date before the fiscal year start month belongs to the fiscal year that started the previous calendar year', function () {
    $date = Carbon::parse('2026-02-15');

    expect(FiscalYear::label($date, startMonth: 4, short: false))->toBe('25-26')
        ->and(FiscalYear::label($date, startMonth: 4, short: true))->toBe('2526');
});

test('a date on or after the fiscal year start month belongs to the fiscal year starting that same calendar year', function () {
    $date = Carbon::parse('2026-04-01');

    expect(FiscalYear::label($date, startMonth: 4, short: false))->toBe('26-27')
        ->and(FiscalYear::label($date, startMonth: 4, short: true))->toBe('2627');
});

test('a null start month falls back to the calendar year', function () {
    $date = Carbon::parse('2026-02-15');

    expect(FiscalYear::label($date, startMonth: null, short: false))->toBe('26-27');
});
