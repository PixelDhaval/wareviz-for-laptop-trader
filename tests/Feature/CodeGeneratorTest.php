<?php

use App\Enums\CodeDateFormat;
use App\Enums\CodeDateSource;
use App\Enums\CodeSegmentPosition;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shipment;
use Illuminate\Support\Carbon;

test('a laptop asset code uses the default prefix and 6-digit sequence when nothing is configured', function () {
    $laptop = Laptop::factory()->create();

    expect($laptop->asset_code)->toBe('WV000001');
});

test('a laptop asset code applies the configured prefix, suffix, separator and sequence pad', function () {
    Setting::factory()->create([
        'laptop_code_prefix' => 'AST',
        'laptop_code_suffix' => 'IN',
        'laptop_code_separator' => '-',
        'laptop_code_sequence_pad' => 3,
    ]);

    $laptop = Laptop::factory()->create();

    expect($laptop->asset_code)->toBe('AST-001-IN');
});

test('a laptop asset code embeds a date segment sourced from the shipment\'s received date', function () {
    Setting::factory()->create([
        'laptop_code_date_source' => CodeDateSource::ShipmentReceivedAt,
        'laptop_code_date_format' => CodeDateFormat::YearMonth,
        'laptop_code_separator' => '-',
    ]);

    $shipment = Shipment::factory()->create(['received_at' => '2026-03-10']);
    $laptop = Laptop::factory()->for($shipment)->create();

    expect($laptop->asset_code)->toBe('WV-2603-000001');
});

test('a laptop asset code embeds a date segment sourced from its own creation date', function () {
    Setting::factory()->create([
        'laptop_code_date_source' => CodeDateSource::CreatedAt,
        'laptop_code_date_format' => CodeDateFormat::Year4,
        'laptop_code_separator' => '-',
    ]);

    $laptop = Laptop::factory()->create();

    expect($laptop->asset_code)->toBe('WV-'.now()->format('Y').'-000001');
});

test('a laptop asset code sequence resets monthly when using the year-month format', function () {
    Setting::factory()->create([
        'laptop_code_date_source' => CodeDateSource::CreatedAt,
        'laptop_code_date_format' => CodeDateFormat::YearMonth,
        'laptop_code_separator' => '-',
    ]);

    Carbon::setTestNow('2026-03-15');
    $marchFirst = Laptop::factory()->create();
    $marchSecond = Laptop::factory()->create();

    Carbon::setTestNow('2026-04-01');
    $april = Laptop::factory()->create();

    Carbon::setTestNow();

    expect($marchFirst->asset_code)->toBe('WV-2603-000001')
        ->and($marchSecond->asset_code)->toBe('WV-2603-000002')
        ->and($april->asset_code)->toBe('WV-2604-000001');
});

test('a laptop asset code sequence resets daily when using the year-month-day format', function () {
    Setting::factory()->create([
        'laptop_code_date_source' => CodeDateSource::CreatedAt,
        'laptop_code_date_format' => CodeDateFormat::YearMonthDay,
        'laptop_code_separator' => '-',
    ]);

    Carbon::setTestNow('2026-03-15 10:00:00');
    $first = Laptop::factory()->create();
    $second = Laptop::factory()->create();

    Carbon::setTestNow('2026-03-16 09:00:00');
    $third = Laptop::factory()->create();

    Carbon::setTestNow();

    expect($first->asset_code)->toBe('WV-260315-000001')
        ->and($second->asset_code)->toBe('WV-260315-000002')
        ->and($third->asset_code)->toBe('WV-260316-000001');
});

test('a sale code applies the configured prefix, suffix, separator and sequence pad', function () {
    Setting::factory()->create([
        'sale_code_prefix' => 'SL',
        'sale_code_suffix' => 'X',
        'sale_code_separator' => '/',
        'sale_code_sequence_pad' => 5,
    ]);

    $sale = Sale::factory()->create();

    expect($sale->code)->toBe('SL/00001/X');
});

test('a sale code places the date segment before or after the sequence number as configured', function () {
    Setting::factory()->create([
        'sale_code_date_format' => CodeDateFormat::YearMonth,
        'sale_code_date_position' => CodeSegmentPosition::After,
        'sale_code_separator' => '-',
    ]);

    $sale = Sale::factory()->create(['sold_at' => '2026-06-01']);

    expect($sale->code)->toBe('0001-2606');
});

test('a sale code sequence resets when the date segment\'s period changes and continues within the same period', function () {
    Setting::factory()->create([
        'sale_code_date_format' => CodeDateFormat::YearMonth,
        'sale_code_separator' => '-',
    ]);

    $juneFirst = Sale::factory()->create(['sold_at' => '2026-06-01']);
    $juneSecond = Sale::factory()->create(['sold_at' => '2026-06-15']);
    $july = Sale::factory()->create(['sold_at' => '2026-07-01']);

    expect($juneFirst->code)->toBe('2606-0001')
        ->and($juneSecond->code)->toBe('2606-0002')
        ->and($july->code)->toBe('2607-0001');
});

test('a sale code sequence resets yearly when using the year format', function () {
    Setting::factory()->create([
        'sale_code_date_format' => CodeDateFormat::Year4,
        'sale_code_separator' => '-',
    ]);

    $first2026 = Sale::factory()->create(['sold_at' => '2026-06-01']);
    $second2026 = Sale::factory()->create(['sold_at' => '2026-11-01']);
    $first2027 = Sale::factory()->create(['sold_at' => '2027-01-01']);

    expect($first2026->code)->toBe('2026-0001')
        ->and($second2026->code)->toBe('2026-0002')
        ->and($first2027->code)->toBe('2027-0001');
});

test('a sale code fiscal year date segment uses the configured fiscal year start month', function () {
    Setting::factory()->create([
        'fiscal_year_start_month' => 4,
        'sale_code_date_format' => CodeDateFormat::FiscalYearLong,
        'sale_code_separator' => '-',
    ]);

    $sale = Sale::factory()->create(['sold_at' => '2026-02-15']);

    expect($sale->code)->toBe('25-26-0001');
});

test('a sale keeps an explicitly provided code instead of generating one', function () {
    $sale = Sale::factory()->create(['code' => 'CUSTOM-CODE']);

    expect($sale->code)->toBe('CUSTOM-CODE');
});
