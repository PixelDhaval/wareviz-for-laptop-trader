<?php

use App\Filament\Resources\Laptops\Pages\ListLaptops;
use App\Models\Laptop;
use App\Models\User;
use Livewire\Livewire;

test('the laptops list page loads with its filters', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $laptop = Laptop::factory()->create();

    Livewire::test(ListLaptops::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$laptop])
        ->assertTableFilterExists('shipment_id')
        ->assertTableFilterExists('status')
        ->assertTableFilterExists('has_issues')
        ->assertTableFilterExists('created_at');
});

test('the shipment filter narrows the laptops list', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $matching = Laptop::factory()->create();
    $other = Laptop::factory()->create();

    Livewire::test(ListLaptops::class)
        ->assertCanSeeTableRecords([$matching, $other])
        ->filterTable('shipment_id', [$matching->shipment_id])
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});
