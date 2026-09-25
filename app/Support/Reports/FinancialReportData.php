<?php

namespace App\Support\Reports;

use App\Enums\ShipmentCostType;
use App\Models\Laptop;
use App\Models\RepairJob;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shipment;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Money-safe cross-row aggregations for the Reports pages. Every total here
 * is summed in PHP over each row's *_in_base_currency accessor — never a
 * raw SQL SUM() on a currency column — per the project rule that a raw SQL
 * sum silently mixes currencies once a row isn't in the base currency (see
 * .ai/rules/models.md).
 */
class FinancialReportData
{
    /**
     * Sale items whose sale falls within the range, eager-loaded with their
     * sale for sold_at and price_in_base_currency's own columns.
     *
     * @return Collection<int, SaleItem>
     */
    public static function saleItemsInRange(?Carbon $from, ?Carbon $to): Collection
    {
        return SaleItem::query()
            ->whereHas('sale', fn ($query) => $query
                ->when($from, fn ($query) => $query->whereDate('sold_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('sold_at', '<=', $to)))
            ->with('sale')
            ->get();
    }

    /**
     * Shipments whose expense-recognition date (invoice_date, falling back
     * to received_at when unset) falls within the range, eager-loaded with
     * laptops (and each laptop's sale item / repair jobs) for
     * container-profit math. Each laptop's `shipment` relation is set back
     * to the already-loaded shipment (with laptops_count) so Laptop's
     * purchase_cost Attribute doesn't re-query it per laptop.
     *
     * @return Collection<int, Shipment>
     */
    public static function shipmentsInRange(?Carbon $from, ?Carbon $to): Collection
    {
        return Shipment::query()
            ->withCount('laptops')
            ->with(['supplier', 'laptops' => fn ($query) => $query->with(['saleItem', 'repairJobs'])])
            ->when($from, fn ($query) => $query->where(fn ($query) => $query
                ->whereDate('invoice_date', '>=', $from)
                ->orWhere(fn ($query) => $query->whereNull('invoice_date')->whereDate('received_at', '>=', $from))))
            ->when($to, fn ($query) => $query->where(fn ($query) => $query
                ->whereDate('invoice_date', '<=', $to)
                ->orWhere(fn ($query) => $query->whereNull('invoice_date')->whereDate('received_at', '<=', $to))))
            ->get()
            ->each(fn (Shipment $shipment) => $shipment->laptops->each->setRelation('shipment', $shipment));
    }

    /**
     * Repair jobs sent within the range, eager-loaded with agency/laptop for
     * cost_in_base_currency's consumers.
     *
     * @return Collection<int, RepairJob>
     */
    public static function repairJobsInRange(?Carbon $from, ?Carbon $to): Collection
    {
        return RepairJob::query()
            ->when($from, fn ($query) => $query->whereDate('sent_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('sent_at', '<=', $to))
            ->with(['agency', 'laptop'])
            ->get();
    }

    /**
     * Realized profit per shipment (container): only laptops that have
     * actually sold count — using each laptop's own sale_price/total_cost —
     * so unsold stock contributes nothing until it sells.
     *
     * @return Collection<int, array{shipment: Shipment, laptops_total: int, laptops_sold: int, cost: string, revenue: string, profit: string}>
     */
    public static function containerProfit(?Carbon $from, ?Carbon $to): Collection
    {
        return static::shipmentsInRange($from, $to)->map(function (Shipment $shipment): array {
            $soldLaptops = $shipment->laptops->filter(fn (Laptop $laptop): bool => $laptop->saleItem !== null);

            $revenue = $soldLaptops->reduce(
                fn (string $total, Laptop $laptop): string => bcadd($total, $laptop->sale_price ?? '0', 8),
                '0',
            );
            $cost = $soldLaptops->reduce(
                fn (string $total, Laptop $laptop): string => bcadd($total, $laptop->total_cost ?? '0', 8),
                '0',
            );

            return [
                'shipment' => $shipment,
                'laptops_total' => $shipment->laptops->count(),
                'laptops_sold' => $soldLaptops->count(),
                'cost' => Money::roundToCents($cost),
                'revenue' => Money::roundToCents($revenue),
                'profit' => Money::roundSignedToCents(bcsub($revenue, $cost, 8)),
            ];
        });
    }

    /**
     * Revenue, expense and net for each calendar month between $from and
     * $to (inclusive), in chronological order. Revenue is recognized at
     * Sale::sold_at; expense is shipment landed cost (recognized at
     * invoice_date, falling back to received_at) plus repair cost
     * (recognized at sent_at). This is a cash-flow-style monthly net,
     * distinct from containerProfit()'s itemized realized profit (which
     * nets each laptop's own historical purchase cost against its sale,
     * regardless of which month it was purchased in).
     *
     * @return Collection<int, array{month: Carbon, units_sold: int, revenue: string, expense: string, net: string}>
     */
    public static function monthlyRevenueAndExpense(Carbon $from, Carbon $to): Collection
    {
        $saleItems = static::saleItemsInRange($from, $to);
        $shipments = static::shipmentsInRange($from, $to);
        $repairJobs = static::repairJobsInRange($from, $to);

        $months = collect();
        $cursor = $from->copy()->startOfMonth();
        $lastMonth = $to->copy()->startOfMonth();

        while ($cursor->lte($lastMonth)) {
            $months->push($cursor->copy());
            $cursor->addMonthNoOverflow();
        }

        return $months->map(function (Carbon $month) use ($saleItems, $shipments, $repairJobs): array {
            $monthSaleItems = $saleItems->filter(fn (SaleItem $item): bool => $item->sale->sold_at?->isSameMonth($month) ?? false);

            $revenue = $monthSaleItems->reduce(
                fn (string $total, SaleItem $item): string => bcadd($total, $item->price_in_base_currency, 8),
                '0',
            );

            $shipmentExpense = $shipments
                ->filter(fn (Shipment $shipment): bool => ($shipment->invoice_date ?? $shipment->received_at)?->isSameMonth($month) ?? false)
                ->reduce(fn (string $total, Shipment $shipment): string => bcadd($total, $shipment->total_cost, 8), '0');

            $repairExpense = $repairJobs
                ->filter(fn (RepairJob $job): bool => $job->sent_at?->isSameMonth($month) ?? false)
                ->reduce(fn (string $total, RepairJob $job): string => bcadd($total, $job->cost_in_base_currency, 8), '0');

            $expense = bcadd($shipmentExpense, $repairExpense, 8);

            return [
                'month' => $month,
                'units_sold' => $monthSaleItems->count(),
                'revenue' => Money::roundToCents($revenue),
                'expense' => Money::roundToCents($expense),
                'net' => Money::roundSignedToCents(bcsub($revenue, $expense, 8)),
            ];
        });
    }

    /**
     * Expense broken down by category: one row per ShipmentCostType (summed
     * across every shipment in range) plus one row per repair agency (an
     * "In-house" bucket for jobs with no agency), zero-amount rows dropped,
     * largest first.
     *
     * @return Collection<int, array{label: string, amount: string}>
     */
    public static function expenseByCategory(?Carbon $from, ?Carbon $to): Collection
    {
        $shipments = static::shipmentsInRange($from, $to);
        $repairJobs = static::repairJobsInRange($from, $to);

        $shipmentRows = collect(ShipmentCostType::cases())->map(function (ShipmentCostType $type) use ($shipments): array {
            $amount = $shipments->reduce(
                fn (string $total, Shipment $shipment): string => bcadd($total, $shipment->costInBaseCurrency($type), 8),
                '0',
            );

            return ['label' => $type->getLabel(), 'amount' => Money::roundToCents($amount)];
        });

        $repairRows = $repairJobs
            ->groupBy(fn (RepairJob $job): string => $job->agency?->name ?? 'In-house')
            ->map(fn (Collection $jobs, string $agency): array => [
                'label' => "Repair — {$agency}",
                'amount' => Money::roundToCents($jobs->reduce(
                    fn (string $total, RepairJob $job): string => bcadd($total, $job->cost_in_base_currency, 8),
                    '0',
                )),
            ])
            ->values();

        return $shipmentRows->concat($repairRows)
            ->filter(fn (array $row): bool => bccomp($row['amount'], '0', 2) === 1)
            ->sortByDesc(fn (array $row): float => (float) $row['amount'])
            ->values();
    }

    /**
     * A unified chronological ledger: one row per Sale (revenue in), per
     * Shipment (purchase cost out) and per RepairJob with a real cost
     * (expense out), newest first.
     *
     * @return Collection<int, array{date: Carbon, type: string, reference: string, description: string, amount: string, direction: string}>
     */
    public static function transactions(?Carbon $from, ?Carbon $to): Collection
    {
        $sales = Sale::query()
            ->when($from, fn ($query) => $query->whereDate('sold_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('sold_at', '<=', $to))
            ->with(['buyer', 'saleItems'])
            ->get()
            ->map(fn (Sale $sale): array => [
                'date' => $sale->sold_at,
                'type' => 'Sale',
                'reference' => $sale->code,
                'description' => sprintf(
                    '%d laptop(s) to %s',
                    $sale->saleItems->count(),
                    $sale->buyer?->name ?? 'walk-in buyer',
                ),
                'amount' => $sale->total_sale_value,
                'direction' => 'in',
            ]);

        $shipments = static::shipmentsInRange($from, $to)->map(fn (Shipment $shipment): array => [
            'date' => $shipment->invoice_date ?? $shipment->received_at,
            'type' => 'Purchase',
            'reference' => $shipment->code,
            'description' => sprintf(
                '%s — %d laptop(s)',
                $shipment->supplier?->name ?? 'Unknown supplier',
                $shipment->laptops_count,
            ),
            'amount' => $shipment->total_cost,
            'direction' => 'out',
        ]);

        $repairJobs = static::repairJobsInRange($from, $to)
            ->filter(fn (RepairJob $job): bool => bccomp($job->cost_in_base_currency, '0', 2) === 1)
            ->map(fn (RepairJob $job): array => [
                'date' => $job->sent_at,
                'type' => 'Repair',
                'reference' => $job->laptop?->asset_code ?? '—',
                'description' => $job->type->getLabel(),
                'amount' => Money::roundToCents($job->cost_in_base_currency),
                'direction' => 'out',
            ]);

        return $sales->concat($shipments)->concat($repairJobs)
            ->filter(fn (array $row): bool => $row['date'] !== null)
            ->sortByDesc(fn (array $row) => $row['date'])
            ->values();
    }
}
