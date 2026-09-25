<?php

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\RelationManagers\ShipmentsRelationManager;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

test('a super_admin can create a supplier', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Livewire::test(CreateSupplier::class)
        ->fillForm([
            'name' => 'Global Traders Ltd',
            'contact_person' => 'Jane Doe',
            'phone' => '555-0100',
            'email' => 'jane@globaltraders.test',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $supplier = Supplier::where('name', 'Global Traders Ltd')->firstOrFail();

    expect($supplier->contact_person)->toBe('Jane Doe')
        ->and($supplier->email)->toBe('jane@globaltraders.test');
});

test('a supplier name must be unique', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Supplier::factory()->create(['name' => 'Duplicate Co']);

    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'Duplicate Co'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

test('a super_admin can edit a supplier', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $supplier = Supplier::factory()->create();

    Livewire::test(EditSupplier::class, ['record' => $supplier->getKey()])
        ->fillForm(['name' => 'Renamed Supplier'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($supplier->fresh()->name)->toBe('Renamed Supplier');
});

test('the suppliers list shows every supplier', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $suppliers = Supplier::factory()->count(3)->create();

    Livewire::test(ListSuppliers::class)
        ->assertCanSeeTableRecords($suppliers);
});

test('the shipments relation manager lists a supplier\'s shipments', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $supplier = Supplier::factory()->create();
    $shipment = Shipment::factory()->for($supplier)->create();
    $otherShipment = Shipment::factory()->create();

    Livewire::test(ShipmentsRelationManager::class, [
        'ownerRecord' => $supplier,
        'pageClass' => EditSupplier::class,
    ])
        ->assertCanSeeTableRecords([$shipment])
        ->assertCanNotSeeTableRecords([$otherShipment]);
});
