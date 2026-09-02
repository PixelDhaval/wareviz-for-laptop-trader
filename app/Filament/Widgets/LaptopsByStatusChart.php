<?php

namespace App\Filament\Widgets;

use App\Enums\LaptopStatus;
use App\Models\Laptop;
use Filament\Widgets\ChartWidget;

class LaptopsByStatusChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Laptops by Status';

    /**
     * Hex equivalents of the Filament color names used by LaptopStatus::getColor(),
     * so the chart always has a color for every case without a positional array
     * silently running out of entries when a new status is added.
     *
     * @var array<string, string>
     */
    protected const COLOR_HEX = [
        'success' => '#22c55e',
        'warning' => '#eab308',
        'gray' => '#6b7280',
        'danger' => '#ef4444',
        'info' => '#3b82f6',
        'primary' => '#f59e0b',
    ];

    protected function getData(): array
    {
        $counts = Laptop::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $cases = LaptopStatus::cases();

        return [
            'datasets' => [
                [
                    'data' => collect($cases)->map(fn (LaptopStatus $status) => $counts[$status->value] ?? 0)->all(),
                    'backgroundColor' => collect($cases)->map(fn (LaptopStatus $status) => self::COLOR_HEX[$status->getColor()] ?? '#94a3b8')->all(),
                ],
            ],
            'labels' => collect($cases)->map(fn (LaptopStatus $status) => $status->getLabel())->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
