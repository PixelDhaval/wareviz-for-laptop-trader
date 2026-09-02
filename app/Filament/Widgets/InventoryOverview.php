<?php

namespace App\Filament\Widgets;

use App\Enums\JobStatus;
use App\Enums\LaptopStatus;
use App\Models\Laptop;
use App\Models\RepairJob;
use App\Models\Shipment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class InventoryOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $laptopsTrend = $this->monthlyCounts(Laptop::class, 'created_at');
        $shipmentsTrend = $this->monthlyCounts(Shipment::class, 'received_at');
        $expenseTrend = $this->monthlySum(RepairJob::class, 'created_at', 'cost');

        $inStock = Laptop::query()->where('status', LaptopStatus::InStock)->count();
        $needsAttention = Laptop::query()->where('has_issues', true)->count();
        $activeRepairJobs = RepairJob::query()->whereIn('status', [JobStatus::Pending, JobStatus::InProgress])->count();

        return [
            Stat::make('Laptops Imported This Month', $laptopsTrend[5])
                ->description(number_format($laptopsTrend[4]).' last month')
                ->descriptionIcon($this->trendIcon($laptopsTrend[5], $laptopsTrend[4]))
                ->chart($laptopsTrend)
                ->color($this->trendColor($laptopsTrend[5], $laptopsTrend[4])),

            Stat::make('In Stock', $inStock)
                ->description('Units currently available')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success'),

            Stat::make('Needs Attention', $needsAttention)
                ->description('Units with a flagged condition issue')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($needsAttention > 0 ? 'danger' : 'gray'),

            Stat::make('Shipments This Month', $shipmentsTrend[5])
                ->description(number_format($shipmentsTrend[4]).' last month')
                ->descriptionIcon($this->trendIcon($shipmentsTrend[5], $shipmentsTrend[4]))
                ->chart($shipmentsTrend)
                ->color($this->trendColor($shipmentsTrend[5], $shipmentsTrend[4])),

            Stat::make('Active Repair Jobs', $activeRepairJobs)
                ->description('Pending or in progress, self or agency')
                ->descriptionIcon(Heroicon::OutlinedWrenchScrewdriver)
                ->color($activeRepairJobs > 0 ? 'info' : 'gray'),

            Stat::make('Repair Expense This Month', number_format($expenseTrend[5], 2))
                ->description(number_format($expenseTrend[4], 2).' last month')
                ->descriptionIcon($this->trendIcon($expenseTrend[5], $expenseTrend[4]))
                ->chart($expenseTrend)
                ->color($this->trendColor($expenseTrend[5], $expenseTrend[4])),
        ];
    }

    /**
     * Counts per month for the last 6 months, oldest first, current month last (index 5).
     *
     * @param  class-string<Laptop|Shipment>  $model
     * @return array<int, int>
     */
    private function monthlyCounts(string $model, string $dateColumn): array
    {
        return collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($model, $dateColumn): int {
                $month = Carbon::now()->subMonthsNoOverflow($monthsAgo);

                return $model::query()
                    ->whereYear($dateColumn, $month->year)
                    ->whereMonth($dateColumn, $month->month)
                    ->count();
            })
            ->values()
            ->all();
    }

    /**
     * Sums per month for the last 6 months, oldest first, current month last (index 5).
     *
     * @return array<int, float>
     */
    private function monthlySum(string $model, string $dateColumn, string $sumColumn): array
    {
        return collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($model, $dateColumn, $sumColumn): float {
                $month = Carbon::now()->subMonthsNoOverflow($monthsAgo);

                return (float) $model::query()
                    ->whereYear($dateColumn, $month->year)
                    ->whereMonth($dateColumn, $month->month)
                    ->sum($sumColumn);
            })
            ->values()
            ->all();
    }

    private function trendIcon(int|float $current, int|float $previous): Heroicon
    {
        return match (true) {
            $current > $previous => Heroicon::OutlinedArrowTrendingUp,
            $current < $previous => Heroicon::OutlinedArrowTrendingDown,
            default => Heroicon::OutlinedMinus,
        };
    }

    private function trendColor(int|float $current, int|float $previous): string
    {
        return match (true) {
            $current > $previous => 'success',
            $current < $previous => 'danger',
            default => 'gray',
        };
    }
}
