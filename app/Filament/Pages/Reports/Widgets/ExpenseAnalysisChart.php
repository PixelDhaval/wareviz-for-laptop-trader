<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Support\Reports\FinancialReportData;
use App\Support\Reports\ReportDateRange;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ExpenseAnalysisChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Expense by category';

    protected function getData(): array
    {
        $range = ReportDateRange::fromFilters($this->pageFilters ?? []);
        $rows = FinancialReportData::expenseByCategory($range->from, $range->to);

        return [
            'datasets' => [
                [
                    'data' => $rows->pluck('amount')->map(fn (string $amount): float => (float) $amount)->all(),
                    'backgroundColor' => [
                        '#f59e0b', '#3b82f6', '#22c55e', '#ef4444', '#a855f7',
                        '#06b6d4', '#eab308', '#ec4899', '#84cc16', '#f97316',
                    ],
                ],
            ],
            'labels' => $rows->pluck('label')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
