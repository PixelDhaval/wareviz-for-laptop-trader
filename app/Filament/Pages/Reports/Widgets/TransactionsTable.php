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

class TransactionsTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Transactions')
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                $range = ReportDateRange::fromFilters($this->pageFilters ?? []);
                $rows = FinancialReportData::transactions($range->from, $range->to);

                return new LengthAwarePaginator(
                    $rows->forPage($page, $recordsPerPage)->values(),
                    total: $rows->count(),
                    perPage: $recordsPerPage,
                    currentPage: $page,
                );
            })
            ->columns([
                TextColumn::make('date')
                    ->state(fn (array $record): string => $record['date']->format('d M Y')),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (array $record): string => match ($record['type']) {
                        'Sale' => 'success',
                        'Purchase' => 'warning',
                        'Repair' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('reference'),
                TextColumn::make('description'),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (array $record): string => ($record['direction'] === 'out' ? '-' : '+')
                        .Money::currencyPrefix(Currency::base())
                        .number_format((float) $record['amount'], 2))
                    ->color(fn (array $record): string => $record['direction'] === 'out' ? 'danger' : 'success'),
            ])
            ->emptyStateHeading('No transactions in this range');
    }
}
