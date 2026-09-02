<?php

namespace App\Filament\Widgets;

use App\Enums\JobStatus;
use App\Models\RepairJob;
use Filament\Widgets\ChartWidget;

class RepairJobsByStatusChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Repair Jobs by Status';

    /**
     * @var array<string, string>
     */
    protected const COLOR_HEX = [
        'success' => '#22c55e',
        'warning' => '#eab308',
        'gray' => '#6b7280',
        'danger' => '#ef4444',
        'info' => '#3b82f6',
    ];

    protected function getData(): array
    {
        $counts = RepairJob::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $cases = JobStatus::cases();

        return [
            'datasets' => [
                [
                    'data' => collect($cases)->map(fn (JobStatus $status) => $counts[$status->value] ?? 0)->all(),
                    'backgroundColor' => collect($cases)->map(fn (JobStatus $status) => self::COLOR_HEX[$status->getColor()] ?? '#94a3b8')->all(),
                ],
            ],
            'labels' => collect($cases)->map(fn (JobStatus $status) => $status->getLabel())->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
