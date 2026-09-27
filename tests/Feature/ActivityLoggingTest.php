<?php

use App\Models\Buyer;
use App\Models\Laptop;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Supplier;
use Spatie\Activitylog\Models\Activity;

function assertLogged(string $subjectType, int|string $subjectId, string $event): Activity
{
    $activity = Activity::query()
        ->where('subject_type', $subjectType)
        ->where('subject_id', $subjectId)
        ->where('event', $event)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();

    return $activity;
}

test('creating and updating a laptop logs activity', function () {
    $laptop = Laptop::factory()->create();

    assertLogged(Laptop::class, $laptop->id, 'created');

    $laptop->update(['issues' => 'Cracked hinge']);

    $activity = assertLogged(Laptop::class, $laptop->id, 'updated');

    expect($activity->attribute_changes['attributes'])->toHaveKey('issues', 'Cracked hinge');
});

test('creating and updating a shipment logs activity', function () {
    $shipment = Shipment::factory()->create(['name' => 'First batch']);

    assertLogged(Shipment::class, $shipment->id, 'created');

    $shipment->update(['name' => 'Renamed batch']);

    $activity = assertLogged(Shipment::class, $shipment->id, 'updated');

    expect($activity->attribute_changes['attributes'])->toBe(['name' => 'Renamed batch'])
        ->and($activity->attribute_changes['old'])->toBe(['name' => 'First batch']);
});

test('creating and updating a sale logs activity', function () {
    $sale = Sale::factory()->create();

    assertLogged(Sale::class, $sale->id, 'created');

    $sale->update(['notes' => 'Called buyer to confirm delivery']);

    $activity = assertLogged(Sale::class, $sale->id, 'updated');

    expect($activity->attribute_changes['attributes'])->toBe(['notes' => 'Called buyer to confirm delivery']);
});

test('creating and updating a sale item logs activity', function () {
    $item = SaleItem::factory()->create();

    assertLogged(SaleItem::class, $item->id, 'created');

    $item->update(['price' => '999.99']);

    $activity = assertLogged(SaleItem::class, $item->id, 'updated');

    expect($activity->attribute_changes['attributes'])->toHaveKey('price', '999.99');
});

test('creating and updating a repair job logs activity', function () {
    $job = RepairJob::factory()->create(['notes' => null]);

    assertLogged(RepairJob::class, $job->id, 'created');

    $job->update(['notes' => 'Waiting on a replacement screen']);

    $activity = assertLogged(RepairJob::class, $job->id, 'updated');

    expect($activity->attribute_changes['attributes'])->toBe(['notes' => 'Waiting on a replacement screen']);
});

test('creating and updating a buyer logs activity', function () {
    $buyer = Buyer::factory()->create(['phone' => '1111111111']);

    assertLogged(Buyer::class, $buyer->id, 'created');

    $buyer->update(['phone' => '2222222222']);

    $activity = assertLogged(Buyer::class, $buyer->id, 'updated');

    expect($activity->attribute_changes['attributes'])->toBe(['phone' => '2222222222']);
});

test('creating and updating a supplier logs activity', function () {
    $supplier = Supplier::factory()->create(['phone' => '1111111111']);

    assertLogged(Supplier::class, $supplier->id, 'created');

    $supplier->update(['phone' => '2222222222']);

    $activity = assertLogged(Supplier::class, $supplier->id, 'updated');

    expect($activity->attribute_changes['attributes'])->toBe(['phone' => '2222222222']);
});

test('updating the settings singleton logs activity', function () {
    $setting = Setting::current();

    $setting->update(['fiscal_year_start_month' => 4]);

    $activity = assertLogged(Setting::class, $setting->id, 'updated');

    expect($activity->attribute_changes['attributes'])->toHaveKey('fiscal_year_start_month', 4);
});
