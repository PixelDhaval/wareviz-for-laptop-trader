<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Support\Reports\FinancialReportData;
use App\Support\Reports\ReportDateRange;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlyExpenseRevenueChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Revenue vs. expense';

    protected function getData(): array
    {
        $range = ReportDateRange::fromFilters($this->pageFilters ?? []);
        [$from, $to] = $range->boundedForMonthlyReports();

        $months = FinancialReportData::monthlyRevenueAndExpense($from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $months->pluck('revenue')->map(fn (string $amount): float => (float) $amount)->all(),
                    'backgroundColor' => '#22c55e',
                ],
                [
                    'label' => 'Expense',
                    'data' => $months->pluck('expense')->map(fn (string $amount): float => (float) $amount)->all(),
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => $months->map(fn (array $row): string => $row['month']->format('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
