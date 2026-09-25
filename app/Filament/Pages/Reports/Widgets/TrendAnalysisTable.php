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

class TrendAnalysisTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Monthly trend')
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                $range = ReportDateRange::fromFilters($this->pageFilters ?? []);
                [$from, $to] = $range->boundedForMonthlyReports();

                $months = FinancialReportData::monthlyRevenueAndExpense($from, $to)
                    ->map(fn (array $row): array => [
                        ...$row,
                        'margin' => bccomp($row['revenue'], '0', 2) === 1
                            ? bcmul(bcdiv($row['net'], $row['revenue'], 6), '100', 1)
                            : null,
                    ])
                    ->sortByDesc(fn (array $row) => $row['month'])
                    ->values();

                return new LengthAwarePaginator(
                    $months->forPage($page, $recordsPerPage)->values(),
                    total: $months->count(),
                    perPage: $recordsPerPage,
                    currentPage: $page,
                );
            })
            ->columns([
                TextColumn::make('month')
                    ->label('Month')
                    ->state(fn (array $record): string => $record['month']->format('F Y')),
                TextColumn::make('units_sold')
                    ->label('Units sold'),
                TextColumn::make('revenue')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base())),
                TextColumn::make('expense')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base())),
                TextColumn::make('net')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base()))
                    ->color(fn (array $record): string => bccomp($record['net'], '0', 2) === -1 ? 'danger' : 'success'),
                TextColumn::make('margin')
                    ->label('Margin')
                    ->formatStateUsing(fn (array $record): string => $record['margin'] !== null ? "{$record['margin']}%" : '—'),
            ]);
    }
}
