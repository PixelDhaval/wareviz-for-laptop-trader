<?php

use App\Enums\JobAssignee;
use App\Enums\ShipmentCostType;
use App\Filament\Pages\Reports\ExpenseAnalysisReport;
use App\Filament\Pages\Reports\MonthlyExpenseRevenueReport;
use App\Filament\Pages\Reports\TransactionsReport;
use App\Filament\Pages\Reports\TrendAnalysisReport;
use App\Models\Agency;
use App\Models\Buyer;
use App\Models\Currency;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shipment;
use App\Models\User;
use Livewire\Livewire;

test('all report pages load for a super_admin', function (string $page) {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Livewire::test($page)->assertOk();
})->with([
    MonthlyExpenseRevenueReport::class,
    TransactionsReport::class,
    ExpenseAnalysisReport::class,
    TrendAnalysisReport::class,
]);

test('the monthly expense and revenue report shows this month\'s totals by default', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $inr = Currency::factory()->base()->create(['code' => 'INR']);

    Shipment::factory()
        ->withCost(ShipmentCostType::InvoiceValue, $inr, '1200.00', '1')
        ->create(['received_at' => now(), 'invoice_date' => null]);

    $sale = Sale::factory()->create(['currency_id' => $inr->id, 'exchange_rate' => '1', 'sold_at' => now()]);
    SaleItem::factory()->for($sale)->create([
        'price' => '900.00',
        'price_currency_id' => $inr->id,
        'price_exchange_rate' => '1',
    ]);

    Livewire::test(MonthlyExpenseRevenueReport::class)
        ->assertOk()
        ->assertSee('1,200.00')
        ->assertSee('900.00');
});

test('the transactions report lists a sale and a shipment within the default range', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $inr = Currency::factory()->base()->create(['code' => 'INR']);
    $buyer = Buyer::factory()->create(['name' => 'Ledger Buyer Co']);

    $sale = Sale::factory()->for($buyer, 'buyer')->create([
        'currency_id' => $inr->id,
        'exchange_rate' => '1',
        'sold_at' => now(),
    ]);
    SaleItem::factory()->for($sale)->create([
        'price' => '300.00',
        'price_currency_id' => $inr->id,
        'price_exchange_rate' => '1',
    ]);

    Livewire::test(TransactionsReport::class)
        ->assertOk()
        ->assertSee($sale->code)
        ->assertSee('Ledger Buyer Co');
});

test('the expense analysis report breaks down shipment costs and repair costs by category', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $inr = Currency::factory()->base()->create(['code' => 'INR']);

    Shipment::factory()
        ->withCost(ShipmentCostType::FreightCost, $inr, '400.00', '1')
        ->create(['received_at' => now(), 'invoice_date' => null]);

    $agency = Agency::factory()->create(['name' => 'Ledger Repairs Ltd']);
    RepairJob::factory()->create([
        'agency_id' => $agency->id,
        'assignee' => JobAssignee::Agency,
        'cost' => '75.00',
        'cost_currency_id' => $inr->id,
        'cost_exchange_rate' => '1',
        'sent_at' => now(),
    ]);

    Livewire::test(ExpenseAnalysisReport::class)
        ->assertOk()
        ->assertSee('Freight cost')
        ->assertSee('Ledger Repairs Ltd');
});

test('the trend analysis report shows units sold for the trailing 12 months', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $inr = Currency::factory()->base()->create(['code' => 'INR']);

    $sale = Sale::factory()->create(['currency_id' => $inr->id, 'exchange_rate' => '1', 'sold_at' => now()]);
    SaleItem::factory()->for($sale)->create([
        'price' => '650.00',
        'price_currency_id' => $inr->id,
        'price_exchange_rate' => '1',
    ]);

    Livewire::test(TrendAnalysisReport::class)
        ->assertOk()
        ->assertSee('650.00');
});
