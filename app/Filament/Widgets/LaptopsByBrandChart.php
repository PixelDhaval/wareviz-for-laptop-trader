<?php

namespace App\Filament\Widgets;

use App\Models\Brand;
use Filament\Widgets\ChartWidget;

class LaptopsByBrandChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Laptops by Brand';

    protected function getData(): array
    {
        $brands = Brand::query()
            ->withCount('laptops')
            ->orderByDesc('laptops_count')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Laptops',
                    'data' => $brands->pluck('laptops_count')->all(),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $brands->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
