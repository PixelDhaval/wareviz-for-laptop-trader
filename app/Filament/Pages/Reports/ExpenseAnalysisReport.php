<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportDateRangeFilter;
use App\Filament\Pages\Reports\Widgets\ExpenseAnalysisChart;
use App\Filament\Pages\Reports\Widgets\ExpenseAnalysisTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Expense broken down by category: one row per ShipmentCostType plus one
 * per repair agency — see
 * App\Support\Reports\FinancialReportData::expenseByCategory().
 */
class ExpenseAnalysisReport extends Page
{
    use HasReportDateRangeFilter;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Expense Analysis';

    protected static ?string $title = 'Expense analysis';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    protected function getFooterWidgets(): array
    {
        return [
            ExpenseAnalysisChart::class,
            ExpenseAnalysisTable::class,
        ];
    }
}
