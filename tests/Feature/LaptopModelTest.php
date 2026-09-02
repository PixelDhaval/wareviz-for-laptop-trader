<?php

use App\Enums\LaptopStatus;
use App\Models\Laptop;

test('a laptop is assigned a unique sequential asset code on creation', function () {
    $laptop = Laptop::factory()->create();

    expect($laptop->asset_code)->toBe(sprintf('WV%06d', $laptop->id));
});

test('has_issues is derived from the issues text', function () {
    $withIssues = Laptop::factory()->create(['issues' => 'Glass broken']);
    $withoutIssues = Laptop::factory()->create(['issues' => null]);

    expect($withIssues->has_issues)->toBeTrue()
        ->and($withoutIssues->has_issues)->toBeFalse();
});

test('condition booleans and status enum are cast correctly', function () {
    $laptop = Laptop::factory()->create(['is_battery_ok' => false]);

    expect($laptop->is_battery_ok)->toBeFalse()
        ->and($laptop->status)->toBe(LaptopStatus::InStock);
});
