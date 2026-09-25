<?php

use App\Enums\JobAssignee;
use App\Enums\LaptopStatus;
use App\Enums\ShipmentCostType;
use App\Models\Agency;
use App\Models\Buyer;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shipment;
use App\Support\Reports\FinancialReportData;
use Illuminate\Support\Carbon;

test('container profit only counts laptops that have actually sold', function () {
    $inr = Currency::factory()->base()->create(['code' => 'INR']);

    $shipment = Shipment::factory()
        ->withCost(ShipmentCostType::InvoiceValue, $inr, '1000.00', '1')
        ->create(['received_at' => '2026-03-01']);

    $sold = Laptop::factory()->for($shipment)->create(['status' => LaptopStatus::Sold]);
    $inStock = Laptop::factory()->for($shipment)->create(['status' => LaptopStatus::InStock]);

    $sale = Sale::factory()->create(['currency_id' => $inr->id, 'exchange_rate' => '1']);
    SaleItem::factory()->for($sale)->for($sold, 'laptop')->create([
        'price' => '800.00',
        'price_currency_id' => $inr->id,
        'price_exchange_rate' => '1',
    ]);

    $rows = FinancialReportData::containerProfit(null, null);
    $row = $rows->sole(fn (array $row) => $row['shipment']->is($shipment));

    expect($row['laptops_total'])->toBe(2)
        ->and($row['laptops_sold'])->toBe(1)
        ->and($row['cost'])->toBe('500.00') // half of the 1000 shipment cost, spread across both laptops
        ->and($row['revenue'])->toBe('800.00')
        ->and($row['profit'])->toBe('300.00');
});

test('container profit can be a loss, correctly signed and rounded', function () {
    $inr = Currency::factory()->base()->create(['code' => 'INR']);

    $shipment = Shipment::factory()
        ->withCost(ShipmentCostType::InvoiceValue, $inr, '1000.00', '1')
        ->create();

    $laptop = Laptop::factory()->for($shipment)->create(['status' => LaptopStatus::Sold]);

    $sale = Sale::factory()->create(['currency_id' => $inr->id, 'exchange_rate' => '1']);
    SaleItem::factory()->for($sale)->for($laptop, 'laptop')->create([
        'price' => '600.00',
        'price_currency_id' => $inr->id,
        'price_exchange_rate' => '1',
    ]);

    $row = FinancialReportData::containerProfit(null, null)->sole(fn (array $row) => $row['shipment']->is($shipment));

    expect($row['cost'])->toBe('1000.00')
        ->and($row['revenue'])->toBe('600.00')
        ->and($row['profit'])->toBe('-400.00');
});

test('container profit sums multi-currency shipment costs into the base currency', function () {
    $shipment = shipmentWithMixedCurrencyCosts();

    $row = FinancialReportData::containerProfit(null, null)->sole(fn (array $row) => $row['shipment']->is($shipment));

    expect($row['cost'])->toBe('0.00')
        ->and($row['laptops_total'])->toBe(0);
});

test('monthly revenue and expense are bucketed by their own recognition date and can net negative', function () {
    $inr = Currency::factory()->base()->create(['code' => 'INR']);

    $marchShipment = Shipment::factory()
        ->withCost(ShipmentCostType::InvoiceValue, $inr, '1000.00', '1')
        ->create(['invoice_date' => '2026-03-05', 'received_at' => '2026-03-05']);
    Laptop::factory()->for($marchShipment)->create();

    $marchSale = Sale::factory()->create(['currency_id' => $inr->id, 'exchange_rate' => '1', 'sold_at' => '2026-03-20']);
    SaleItem::factory()->for($marchSale)->create([
        'price' => '200.00',
        'price_currency_id' => $inr->id,
        'price_exchange_rate' => '1',
    ]);

    $aprilSale = Sale::factory()->create(['currency_id' => $inr->id, 'exchange_rate' => '1', 'sold_at' => '2026-04-10']);
    SaleItem::factory()->for($aprilSale)->create([
        'price' => '5000.00',
        'price_currency_id' => $inr->id,
        'price_exchange_rate' => '1',
    ]);

    $months = FinancialReportData::monthlyRevenueAndExpense(
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-04-30'),
    );

    expect($months)->toHaveCount(2);

    $march = $months->first(fn (array $row) => $row['month']->isSameMonth(Carbon::parse('2026-03-01')));
    $april = $months->first(fn (array $row) => $row['month']->isSameMonth(Carbon::parse('2026-04-01')));

    expect($march['revenue'])->toBe('200.00')
        ->and($march['expense'])->toBe('1000.00')
        ->and($march['net'])->toBe('-800.00')
        ->and($march['units_sold'])->toBe(1)
        ->and($april['revenue'])->toBe('5000.00')
        ->and($april['expense'])->toBe('0.00')
        ->and($april['net'])->toBe('5000.00')
        ->and($april['units_sold'])->toBe(1);
});

test('expense by category breaks down shipment cost types and repair agencies, dropping zero rows', function () {
    $inr = Currency::factory()->base()->create(['code' => 'INR']);

    Shipment::factory()
        ->withCost(ShipmentCostType::InvoiceValue, $inr, '1000.00', '1')
        ->withCost(ShipmentCostType::FreightCost, $inr, '200.00', '1')
        ->create();

    $agency = Agency::factory()->create(['name' => 'Acme Repairs']);
    RepairJob::factory()->create([
        'agency_id' => $agency->id,
        'assignee' => JobAssignee::Agency,
        'cost' => '150.00',
        'cost_currency_id' => $inr->id,
        'cost_exchange_rate' => '1',
    ]);

    $rows = FinancialReportData::expenseByCategory(null, null);

    expect($rows->pluck('label'))->toContain('Invoice value', 'Freight cost', 'Repair — Acme Repairs')
        ->and($rows->pluck('label'))->not->toContain('Duty', 'Local expense', 'Other expense')
        ->and($rows->first()['label'])->toBe('Invoice value'); // largest amount first
});

test('transactions ledger lists sales, shipments and repairs with correct direction, newest first', function () {
    $inr = Currency::factory()->base()->create(['code' => 'INR']);
    $buyer = Buyer::factory()->create(['name' => 'Acme Traders']);

    $sale = Sale::factory()->for($buyer, 'buyer')->create([
        'currency_id' => $inr->id,
        'exchange_rate' => '1',
        'sold_at' => '2026-03-20',
    ]);
    SaleItem::factory()->for($sale)->create([
        'price' => '500.00',
        'price_currency_id' => $inr->id,
        'price_exchange_rate' => '1',
    ]);

    $shipment = Shipment::factory()
        ->withCost(ShipmentCostType::InvoiceValue, $inr, '300.00', '1')
        ->create(['received_at' => '2026-03-10', 'invoice_date' => null]);

    $repairJob = RepairJob::factory()->create([
        'cost' => '50.00',
        'cost_currency_id' => $inr->id,
        'cost_exchange_rate' => '1',
        'sent_at' => '2026-03-25',
    ]);

    // Scoped to March 2026: SaleItem::factory()'s own definition() creates a
    // throwaway Sale (dated "today") as a side effect of computing its
    // sale_id default, even though ->for($sale) overrides it — an unbounded
    // range would pick that stray row up too.
    $transactions = FinancialReportData::transactions(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));

    $saleRow = $transactions->sole(fn (array $row) => $row['type'] === 'Sale' && $row['reference'] === $sale->code);
    $shipmentRow = $transactions->sole(fn (array $row) => $row['type'] === 'Purchase' && $row['reference'] === $shipment->code);
    $repairRow = $transactions->sole(fn (array $row) => $row['type'] === 'Repair' && $row['reference'] === $repairJob->laptop->asset_code);

    expect($saleRow['direction'])->toBe('in')
        ->and($saleRow['amount'])->toBe('500.00')
        ->and($shipmentRow['direction'])->toBe('out')
        ->and($shipmentRow['amount'])->toBe('300.00')
        ->and($repairRow['direction'])->toBe('out')
        ->and($repairRow['amount'])->toBe('50.00')
        ->and($transactions->pluck('date')->map->toDateString()->all())->toBe(['2026-03-25', '2026-03-20', '2026-03-10']);
});
