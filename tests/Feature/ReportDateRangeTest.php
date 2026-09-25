<?php

use App\Support\Reports\ReportDateRange;
use Illuminate\Support\Carbon;

test('this_month resolves to the current calendar month', function () {
    Carbon::setTestNow('2026-03-15');

    $range = ReportDateRange::fromFilters(['preset' => 'this_month']);

    expect($range->from->toDateString())->toBe('2026-03-01')
        ->and($range->to->toDateString())->toBe('2026-03-31');

    Carbon::setTestNow();
});

test('last_month resolves to the previous calendar month', function () {
    Carbon::setTestNow('2026-03-15');

    $range = ReportDateRange::fromFilters(['preset' => 'last_month']);

    expect($range->from->toDateString())->toBe('2026-02-01')
        ->and($range->to->toDateString())->toBe('2026-02-28');

    Carbon::setTestNow();
});

test('this_quarter and this_year resolve to their respective bounds', function () {
    Carbon::setTestNow('2026-05-10');

    $quarter = ReportDateRange::fromFilters(['preset' => 'this_quarter']);
    $year = ReportDateRange::fromFilters(['preset' => 'this_year']);

    expect($quarter->from->toDateString())->toBe('2026-04-01')
        ->and($quarter->to->toDateString())->toBe('2026-06-30')
        ->and($year->from->toDateString())->toBe('2026-01-01')
        ->and($year->to->toDateString())->toBe('2026-12-31');

    Carbon::setTestNow();
});

test('last_12_months spans from 11 months ago to the end of the current month', function () {
    Carbon::setTestNow('2026-03-15');

    $range = ReportDateRange::fromFilters(['preset' => 'last_12_months']);

    expect($range->from->toDateString())->toBe('2025-04-01')
        ->and($range->to->toDateString())->toBe('2026-03-31');

    Carbon::setTestNow();
});

test('all_time has no lower or upper bound', function () {
    $range = ReportDateRange::fromFilters(['preset' => 'all_time']);

    expect($range->from)->toBeNull()
        ->and($range->to)->toBeNull();
});

test('boundedForMonthlyReports falls back to the last 12 months when a bound is missing', function () {
    Carbon::setTestNow('2026-03-15');

    [$from, $to] = ReportDateRange::fromFilters(['preset' => 'all_time'])->boundedForMonthlyReports();

    expect($from->toDateString())->toBe('2025-04-01')
        ->and($to->toDateString())->toBe('2026-03-31');

    Carbon::setTestNow();
});

test('boundedForMonthlyReports keeps an explicit bound as-is', function () {
    [$from, $to] = ReportDateRange::fromFilters(['preset' => 'custom', 'from' => '2026-01-05', 'to' => '2026-01-20'])
        ->boundedForMonthlyReports();

    expect($from->toDateString())->toBe('2026-01-05')
        ->and($to->toDateString())->toBe('2026-01-20');
});

test('custom uses the given from/to, leaving either null when unset', function () {
    $both = ReportDateRange::fromFilters(['preset' => 'custom', 'from' => '2026-01-05', 'to' => '2026-01-20']);
    $fromOnly = ReportDateRange::fromFilters(['preset' => 'custom', 'from' => '2026-01-05']);
    $toOnly = ReportDateRange::fromFilters(['preset' => 'custom', 'to' => '2026-01-20']);

    expect($both->from->toDateString())->toBe('2026-01-05')
        ->and($both->to->toDateString())->toBe('2026-01-20')
        ->and($fromOnly->from->toDateString())->toBe('2026-01-05')
        ->and($fromOnly->to)->toBeNull()
        ->and($toOnly->from)->toBeNull()
        ->and($toOnly->to->toDateString())->toBe('2026-01-20');
});

test('a missing or unrecognised preset falls back to this_month', function () {
    Carbon::setTestNow('2026-03-15');

    $missing = ReportDateRange::fromFilters([]);
    $unrecognised = ReportDateRange::fromFilters(['preset' => 'not-a-real-preset']);

    expect($missing->label)->toBe('This month')
        ->and($unrecognised->label)->toBe('This month');

    Carbon::setTestNow();
});
