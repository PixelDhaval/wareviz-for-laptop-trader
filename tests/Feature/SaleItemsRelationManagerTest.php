<?php

use App\Enums\LaptopStatus;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Filament\Resources\Sales\RelationManagers\SaleItemsRelationManager;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Columns\TextInputColumn;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('the relation manager lists the sale\'s laptops', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $item = SaleItem::factory()->for($sale)->create();

    Livewire::test(SaleItemsRelationManager::class, ['ownerRecord' => $sale, 'pageClass' => ViewSale::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$item]);
});

test('the price column is inline-editable', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();

    Livewire::test(SaleItemsRelationManager::class, ['ownerRecord' => $sale, 'pageClass' => ViewSale::class])
        ->assertTableColumnExists('price', fn (TextInputColumn $column): bool => true);
});

test('the table shows the laptop\'s processor and memory', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();

    Livewire::test(SaleItemsRelationManager::class, ['ownerRecord' => $sale, 'pageClass' => ViewSale::class])
        ->assertTableColumnExists('laptop.processor.name')
        ->assertTableColumnExists('laptop.ram_gb')
        ->assertTableColumnExists('laptop.storage_gb');
});

test('only in-stock laptops are offered when adding one to a sale', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $inStock = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    Livewire::test(SaleItemsRelationManager::class, ['ownerRecord' => $sale, 'pageClass' => ViewSale::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'laptop_id' => $inStock->id,
            'price' => '150',
        ])
        ->assertHasNoActionErrors();

    $item = SaleItem::query()->where('sale_id', $sale->id)->where('laptop_id', $inStock->id)->sole();

    expect($item->price)->toBe('150.00')
        ->and($item->price_currency_id)->toBe($sale->currency_id)
        ->and($item->price_exchange_rate)->toBe($sale->exchange_rate)
        ->and($inStock->fresh()->status)->toBe(LaptopStatus::Sold);
});

test('a user who can view but not update sales cannot add or remove laptops', function () {
    $role = Role::create(['name' => 'sales_viewer']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'View:Sale', 'guard_name' => 'web']));
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'ViewAny:Sale', 'guard_name' => 'web']));

    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $item = SaleItem::factory()->for($sale)->create();

    Livewire::test(SaleItemsRelationManager::class, ['ownerRecord' => $sale, 'pageClass' => ViewSale::class])
        ->assertActionHidden(TestAction::make(CreateAction::class)->table())
        ->assertActionHidden(TestAction::make(DeleteAction::class)->table($item));
});

test('removing a laptop from a sale via the relation manager returns it to stock', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $item = SaleItem::factory()->for($sale)->for($laptop)->create();

    Livewire::test(SaleItemsRelationManager::class, ['ownerRecord' => $sale, 'pageClass' => ViewSale::class])
        ->callAction(TestAction::make(DeleteAction::class)->table($item));

    $this->assertModelMissing($item);
    expect($laptop->fresh()->status)->toBe(LaptopStatus::InStock);
});
