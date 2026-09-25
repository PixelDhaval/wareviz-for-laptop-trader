<?php

use App\Enums\LaptopStatus;
use App\Enums\ShipmentCostType;
use App\Filament\Pages\Reports\ContainerProfitReport;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shipment;
use App\Models\User;
use Livewire\Livewire;

test('the container profit report loads for a super_admin', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Livewire::test(ContainerProfitReport::class)->assertOk();
});

test('the container profit report shows realized profit per container within the default range', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $inr = Currency::factory()->base()->create(['code' => 'INR']);

    $shipment = Shipment::factory()
        ->withCost(ShipmentCostType::InvoiceValue, $inr, '1000.00', '1')
        ->create(['code' => 'SHP-PROFIT-TEST', 'received_at' => now()]);

    $laptop = Laptop::factory()->for($shipment)->create(['status' => LaptopStatus::Sold]);

    $sale = Sale::factory()->create(['currency_id' => $inr->id, 'exchange_rate' => '1', 'sold_at' => now()]);
    SaleItem::factory()->for($sale)->for($laptop, 'laptop')->create([
        'price' => '1500.00',
        'price_currency_id' => $inr->id,
        'price_exchange_rate' => '1',
    ]);

    $html = Livewire::test(ContainerProfitReport::class)
        ->assertOk()
        ->assertSee('SHP-PROFIT-TEST')
        ->assertSee('500.00') // realized profit: 1500 revenue - 1000 cost
        ->html();

    // Guards against footer widgets being rendered twice (once via a manual
    // content() override, once via the page's automatic {{ $this->footerWidgets }}
    // echo) — each container should appear in the table exactly once.
    expect(substr_count($html, 'SHP-PROFIT-TEST'))->toBe(1);
});
