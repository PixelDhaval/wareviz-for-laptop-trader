<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportDateRangeFilter;
use App\Filament\Pages\Reports\Widgets\TransactionsStats;
use App\Filament\Pages\Reports\Widgets\TransactionsTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * A unified chronological ledger: one row per Sale (in), per Shipment
 * (out) and per RepairJob with a real cost (out) — see
 * App\Support\Reports\FinancialReportData::transactions().
 */
class TransactionsReport extends Page
{
    use HasReportDateRangeFilter;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?string $title = 'Transactions';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected function getFooterWidgets(): array
    {
        return [
            TransactionsStats::class,
            TransactionsTable::class,
        ];
    }
}
