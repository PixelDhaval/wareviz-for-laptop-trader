<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Models\Currency;
use App\Support\Money;
use App\Support\Reports\FinancialReportData;
use App\Support\Reports\ReportDateRange;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\LengthAwarePaginator;

class ContainerProfitTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Profit by container')
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                $range = ReportDateRange::fromFilters($this->pageFilters ?? []);

                $rows = FinancialReportData::containerProfit($range->from, $range->to)
                    ->sortByDesc(fn (array $row): float => (float) $row['profit'])
                    ->values();

                return new LengthAwarePaginator(
                    $rows->forPage($page, $recordsPerPage)->values(),
                    total: $rows->count(),
                    perPage: $recordsPerPage,
                    currentPage: $page,
                );
            })
            ->columns([
                TextColumn::make('code')
                    ->label('Container')
                    ->state(fn (array $record): string => $record['shipment']->code),
                TextColumn::make('supplier')
                    ->label('Supplier')
                    ->state(fn (array $record): string => $record['shipment']->supplier?->name ?? '—'),
                TextColumn::make('sold')
                    ->label('Sold / total')
                    ->state(fn (array $record): string => "{$record['laptops_sold']} / {$record['laptops_total']}"),
                TextColumn::make('cost')
                    ->label('Landed cost')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base())),
                TextColumn::make('revenue')
                    ->label('Realized revenue')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base())),
                TextColumn::make('profit')
                    ->label('Realized profit')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base()))
                    ->color(fn (array $record): string => bccomp($record['profit'], '0', 2) === -1 ? 'danger' : 'success'),
            ])
            ->emptyStateHeading('No shipments in this range');
    }
}
