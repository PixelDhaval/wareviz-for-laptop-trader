<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Models\Currency;
use App\Support\Money;
use App\Support\Reports\FinancialReportData;
use App\Support\Reports\ReportDateRange;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransactionsStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $range = ReportDateRange::fromFilters($this->pageFilters ?? []);
        $transactions = FinancialReportData::transactions($range->from, $range->to);

        $in = $transactions->where('direction', 'in')
            ->reduce(fn (string $total, array $row): string => bcadd($total, $row['amount'], 2), '0');
        $out = $transactions->where('direction', 'out')
            ->reduce(fn (string $total, array $row): string => bcadd($total, $row['amount'], 2), '0');
        $net = Money::roundSignedToCents(bcsub($in, $out, 8));
        $prefix = Money::currencyPrefix(Currency::base());

        return [
            Stat::make('Transactions', (string) $transactions->count()),
            Stat::make('Total in', $prefix.number_format((float) $in, 2))->color('success'),
            Stat::make('Total out', $prefix.number_format((float) $out, 2))->color('danger'),
            Stat::make('Net', $prefix.number_format((float) $net, 2))
                ->color(bccomp($net, '0', 2) === -1 ? 'danger' : 'success'),
        ];
    }
}
