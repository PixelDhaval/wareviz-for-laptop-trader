<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Support\Reports\FinancialReportData;
use App\Support\Reports\ReportDateRange;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TrendAnalysisChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Revenue, expense & net trend';

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
                    'borderColor' => '#22c55e',
                    'fill' => false,
                ],
                [
                    'label' => 'Expense',
                    'data' => $months->pluck('expense')->map(fn (string $amount): float => (float) $amount)->all(),
                    'borderColor' => '#ef4444',
                    'fill' => false,
                ],
                [
                    'label' => 'Net',
                    'data' => $months->pluck('net')->map(fn (string $amount): float => (float) $amount)->all(),
                    'borderColor' => '#3b82f6',
                    'fill' => false,
                ],
            ],
            'labels' => $months->map(fn (array $row): string => $row['month']->format('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
