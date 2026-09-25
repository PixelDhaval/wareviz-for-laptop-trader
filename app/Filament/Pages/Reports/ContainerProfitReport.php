<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportDateRangeFilter;
use App\Filament\Pages\Reports\Widgets\ContainerProfitStats;
use App\Filament\Pages\Reports\Widgets\ContainerProfitTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Realized profit per shipment ("container"): only laptops that have
 * actually sold count toward a container's revenue/cost/profit — unsold
 * stock contributes nothing until it sells (see App\Support\Reports\
 * FinancialReportData::containerProfit()).
 */
class ContainerProfitReport extends Page
{
    use HasReportDateRangeFilter;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Container Profit';

    protected static ?string $title = 'Container profit';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected function getFooterWidgets(): array
    {
        return [
            ContainerProfitStats::class,
            ContainerProfitTable::class,
        ];
    }
}
