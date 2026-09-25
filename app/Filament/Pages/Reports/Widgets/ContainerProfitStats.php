<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Models\Currency;
use App\Support\Money;
use App\Support\Reports\FinancialReportData;
use App\Support\Reports\ReportDateRange;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContainerProfitStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $range = ReportDateRange::fromFilters($this->pageFilters ?? []);
        $rows = FinancialReportData::containerProfit($range->from, $range->to);

        $cost = $rows->reduce(fn (string $total, array $row): string => bcadd($total, $row['cost'], 2), '0');
        $revenue = $rows->reduce(fn (string $total, array $row): string => bcadd($total, $row['revenue'], 2), '0');
        $profit = bcsub($revenue, $cost, 2);
        $margin = bccomp($revenue, '0', 2) === 1 ? bcmul(bcdiv($profit, $revenue, 6), '100', 1) : null;
        $prefix = Money::currencyPrefix(Currency::base());

        return [
            Stat::make('Containers', (string) $rows->count()),
            Stat::make('Landed cost', $prefix.number_format((float) $cost, 2)),
            Stat::make('Realized revenue', $prefix.number_format((float) $revenue, 2)),
            Stat::make('Realized profit', $prefix.number_format((float) $profit, 2))
                ->color(bccomp($profit, '0', 2) === -1 ? 'danger' : 'success')
                ->description($margin !== null ? "{$margin}% margin" : 'No sales yet in this range'),
        ];
    }
}
