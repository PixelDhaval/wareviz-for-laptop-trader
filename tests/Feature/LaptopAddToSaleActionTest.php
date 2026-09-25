<?php

use App\Enums\LaptopStatus;
use App\Filament\Resources\Laptops\Pages\ListLaptops;
use App\Models\Buyer;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Livewire\Livewire;

test('the add to sale action is only visible for in-stock laptops', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $inStock = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $sold = Laptop::factory()->create(['status' => LaptopStatus::Sold]);

    Livewire::test(ListLaptops::class)
        ->assertTableActionVisible('addToSale', $inStock)
        ->assertTableActionHidden('addToSale', $sold);
});

test('adding a laptop to a draft sale creates a sale item at the sale\'s currency and marks it reserved', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create(['is_completed' => false]);
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    Livewire::test(ListLaptops::class)
        ->callTableAction('addToSale', $laptop, data: [
            'sale_id' => $sale->id,
        ])
        ->assertHasNoTableActionErrors();

    $item = SaleItem::query()->where('sale_id', $sale->id)->where('laptop_id', $laptop->id)->sole();

    expect($item->price_currency_id)->toBe($sale->currency_id)
        ->and($item->price_exchange_rate)->toBe($sale->exchange_rate)
        ->and($laptop->fresh()->status)->toBe(LaptopStatus::Reserved);
});

test('adding a laptop to an already-completed sale marks it sold, not reserved', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create(['is_completed' => true]);
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    Livewire::test(ListLaptops::class)
        ->callTableAction('addToSale', $laptop, data: [
            'sale_id' => $sale->id,
        ])
        ->assertHasNoTableActionErrors();

    expect($laptop->fresh()->status)->toBe(LaptopStatus::Sold);
});

test('the bulk add to sale action only adds the selected in-stock laptops', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $sale = Sale::factory()->create();
    $inStock = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $alreadySold = Laptop::factory()->create(['status' => LaptopStatus::Sold]);

    Livewire::test(ListLaptops::class)
        ->callTableBulkAction('addToSale', [$inStock, $alreadySold], data: [
            'sale_id' => $sale->id,
        ]);

    expect(SaleItem::query()->where('sale_id', $sale->id)->pluck('laptop_id')->all())
        ->toBe([$inStock->id]);
});

test('the sale select searches by sale code without preloading every sale', function () {
    $matching = Sale::factory()->create(['code' => 'SALE-MATCH']);
    $nonMatching = Sale::factory()->create(['code' => 'SALE-OTHER']);

    $results = Sale::searchableOptions('MATCH');

    expect($results)->toHaveKey($matching->id)
        ->and($results)->not->toHaveKey($nonMatching->id);
});

test('the sale select also searches by the buyer\'s name', function () {
    $buyer = Buyer::factory()->create(['name' => 'Acme Corp']);
    $matching = Sale::factory()->for($buyer, 'buyer')->create();
    $nonMatching = Sale::factory()->create();

    $results = Sale::searchableOptions('Acme');

    expect($results)->toHaveKey($matching->id)
        ->and($results[$matching->id])->toContain('Acme Corp')
        ->and($results)->not->toHaveKey($nonMatching->id);
});

test('the sale select caps search results so a large sales table stays fast', function () {
    Sale::factory()->count(55)->create();

    expect(Sale::searchableOptions(''))->toHaveCount(50);
});
