<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportDateRangeFilter;
use App\Filament\Pages\Reports\Widgets\MonthlyExpenseRevenueChart;
use App\Filament\Pages\Reports\Widgets\MonthlyExpenseRevenueTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * A cash-flow-style monthly view: revenue recognized at Sale::sold_at vs.
 * expense (shipment landed cost + repair cost) recognized at their own
 * dates — distinct from ContainerProfitReport's itemized realized profit,
 * which nets each sold laptop's own historical purchase cost against its
 * sale (see App\Support\Reports\FinancialReportData::monthlyRevenueAndExpense()).
 */
class MonthlyExpenseRevenueReport extends Page
{
    use HasReportDateRangeFilter;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Monthly Expense & Revenue';

    protected static ?string $title = 'Monthly expense & revenue';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected function getFooterWidgets(): array
    {
        return [
            MonthlyExpenseRevenueChart::class,
            MonthlyExpenseRevenueTable::class,
        ];
    }
}
