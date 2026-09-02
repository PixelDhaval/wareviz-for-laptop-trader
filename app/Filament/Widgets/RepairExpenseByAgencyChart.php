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
        $agencies = Agency::query()
            ->withSum('repairJobs as expense', 'cost')
            ->orderByDesc('expense')
            ->limit(7)
            ->get();

        $inHouseExpense = RepairJob::query()
            ->where('assignee', JobAssignee::InHouse)
            ->sum('cost');

        $labels = $agencies->pluck('name')->push('In-house')->all();
        $data = $agencies->pluck('expense')->map(fn ($value) => (float) $value)->push((float) $inHouseExpense)->all();

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
