<?php

namespace App\Filament\Resources\Buyers\RelationManagers;

use App\Enums\SaleType;
use App\Models\Currency;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    protected static ?string $title = 'Sales';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('currency.code')
                    ->label('Currency'),
                TextColumn::make('total_sale_value')
                    ->label('Total value')
                    ->tooltip('Every item\'s sale price, converted to the base currency')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base())),
                TextColumn::make('sold_at')
                    ->date(),
                IconColumn::make('is_completed')
                    ->boolean(),
            ])
            ->defaultSort('sold_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(SaleType::class)
                    ->multiple(),
                Filter::make('sold_at')
                    ->schema([
                        DatePicker::make('sold_from')->label('Sold from'),
                        DatePicker::make('sold_until')->label('Sold until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['sold_from'], fn (Builder $query, $date): Builder => $query->whereDate('sold_at', '>=', $date))
                            ->when($data['sold_until'], fn (Builder $query, $date): Builder => $query->whereDate('sold_at', '<=', $date));
                    })
                    ->columnSpan(2),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
