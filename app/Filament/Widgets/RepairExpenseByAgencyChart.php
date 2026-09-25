<?php

namespace App\Filament\Widgets;

use App\Enums\JobAssignee;
use App\Models\Agency;
use App\Models\RepairJob;
use Filament\Widgets\ChartWidget;

class RepairExpenseByAgencyChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Repair Expense by Agency';

    protected function getData(): array
    {
        // Each job's cost can be in a different currency, so the total per
        // agency can't be a SQL sum() over the raw `cost` column — it has to
        // be summed in PHP from each job's cost_in_base_currency.
        $agencies = Agency::query()
            ->with('repairJobs')
            ->get()
            ->map(fn (Agency $agency): array => [
                'name' => $agency->name,
                'expense' => (float) $agency->repairJobs->sum(fn (RepairJob $job): string => $job->cost_in_base_currency),
            ])
            ->sortByDesc('expense')
            ->take(7);

        $inHouseExpense = RepairJob::query()
            ->where('assignee', JobAssignee::InHouse)
            ->get()
            ->sum(fn (RepairJob $job): string => $job->cost_in_base_currency);

        $labels = $agencies->pluck('name')->push('In-house')->all();
        $data = $agencies->pluck('expense')->push((float) $inHouseExpense)->all();

        return [
            'datasets' => [
                [
                    'label' => 'Expense',
                    'data' => $data,
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
