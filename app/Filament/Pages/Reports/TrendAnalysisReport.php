<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportDateRangeFilter;
use App\Filament\Pages\Reports\Widgets\TrendAnalysisChart;
use App\Filament\Pages\Reports\Widgets\TrendAnalysisTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Multi-metric trend over the selected range (defaulting to the last 12
 * months for open-ended presets like "All time" — see
 * App\Support\Reports\ReportDateRange::boundedForMonthlyReports()).
 */
class TrendAnalysisReport extends Page
{
    use HasReportDateRangeFilter;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Trend Analysis';

    protected static ?string $title = 'Trend analysis';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 5;

    protected function getFooterWidgets(): array
    {
        return [
            TrendAnalysisChart::class,
            TrendAnalysisTable::class,
        ];
    }
}
