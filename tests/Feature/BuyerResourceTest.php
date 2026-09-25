<?php

use App\Filament\Resources\Buyers\Pages\CreateBuyer;
use App\Filament\Resources\Buyers\Pages\EditBuyer;
use App\Filament\Resources\Buyers\Pages\ListBuyers;
use App\Filament\Resources\Buyers\RelationManagers\SalesRelationManager;
use App\Models\Buyer;
use App\Models\Sale;
use App\Models\User;
use Livewire\Livewire;

test('a super_admin can create a buyer', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Livewire::test(CreateBuyer::class)
        ->fillForm([
            'name' => 'Acme Traders',
            'contact_person' => 'John Smith',
            'phone' => '555-0200',
            'email' => 'john@acmetraders.test',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $buyer = Buyer::where('name', 'Acme Traders')->firstOrFail();

    expect($buyer->contact_person)->toBe('John Smith')
        ->and($buyer->email)->toBe('john@acmetraders.test');
});

test('a buyer name must be unique', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Buyer::factory()->create(['name' => 'Duplicate Buyer']);

    Livewire::test(CreateBuyer::class)
        ->fillForm(['name' => 'Duplicate Buyer'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

test('a super_admin can edit a buyer', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $buyer = Buyer::factory()->create();

    Livewire::test(EditBuyer::class, ['record' => $buyer->getKey()])
        ->fillForm(['name' => 'Renamed Buyer'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($buyer->fresh()->name)->toBe('Renamed Buyer');
});

test('the buyers list shows every buyer', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $buyers = Buyer::factory()->count(3)->create();

    Livewire::test(ListBuyers::class)
        ->assertCanSeeTableRecords($buyers);
});

test('the sales relation manager lists a buyer\'s sales', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $buyer = Buyer::factory()->create();
    $sale = Sale::factory()->for($buyer)->create();
    $otherSale = Sale::factory()->create();

    Livewire::test(SalesRelationManager::class, [
        'ownerRecord' => $buyer,
        'pageClass' => EditBuyer::class,
    ])
        ->assertCanSeeTableRecords([$sale])
        ->assertCanNotSeeTableRecords([$otherSale]);
});
