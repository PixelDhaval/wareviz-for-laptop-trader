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

class ExpenseAnalysisTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Expense breakdown')
            ->records(function (int $page, int $recordsPerPage): LengthAwarePaginator {
                $range = ReportDateRange::fromFilters($this->pageFilters ?? []);
                $rows = FinancialReportData::expenseByCategory($range->from, $range->to);

                $total = $rows->reduce(fn (string $total, array $row): string => bcadd($total, $row['amount'], 2), '0');

                $rows = $rows->map(fn (array $row): array => [
                    ...$row,
                    'share' => bccomp($total, '0', 2) === 1 ? bcmul(bcdiv($row['amount'], $total, 6), '100', 1) : '0',
                ]);

                return new LengthAwarePaginator(
                    $rows->forPage($page, $recordsPerPage)->values(),
                    total: $rows->count(),
                    perPage: $recordsPerPage,
                    currentPage: $page,
                );
            })
            ->columns([
                TextColumn::make('label')
                    ->label('Category'),
                TextColumn::make('amount')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base())),
                TextColumn::make('share')
                    ->label('Share')
                    ->formatStateUsing(fn (array $record): string => "{$record['share']}%"),
            ])
            ->emptyStateHeading('No expenses in this range');
    }
}
