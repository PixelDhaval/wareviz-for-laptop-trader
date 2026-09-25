<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Enums\SaleType;
use App\Models\Currency;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('buyer.name')
                    ->label('Buyer / Consignee')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('destination_country')
                    ->label('Destination')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('currency.code')
                    ->label('Currency'),
                TextColumn::make('sale_items_count')
                    ->label('Laptops')
                    ->counts('saleItems')
                    ->sortable(),
                TextColumn::make('total_sale_value')
                    ->label('Total value')
                    ->tooltip('Every item\'s sale price, converted to the base currency')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base())),
                TextColumn::make('sold_at')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_completed')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sold_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(SaleType::class)
                    ->multiple(),
                SelectFilter::make('currency_id')
                    ->label('Currency')
                    ->relationship('currency', 'code')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('buyer_id')
                    ->label('Buyer / Consignee')
                    ->relationship('buyer', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                TernaryFilter::make('is_completed'),
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
                    ->columnSpan(2)
                    ->columns(2),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
