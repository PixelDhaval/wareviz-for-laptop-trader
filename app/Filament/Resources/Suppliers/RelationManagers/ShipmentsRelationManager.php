<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Models\Currency;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';

    protected static ?string $title = 'Shipments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('received_at')
                    ->date(),
                TextColumn::make('laptops_count')
                    ->label('Laptops')
                    ->counts('laptops'),
                TextColumn::make('total_cost')
                    ->label('Total cost')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base()))
                    ->tooltip('Invoice value plus all expenses, in the base currency'),
                IconColumn::make('is_completed')
                    ->boolean(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                TernaryFilter::make('is_completed'),
                Filter::make('received_at')
                    ->schema([
                        DatePicker::make('received_from')->label('Received from'),
                        DatePicker::make('received_until')->label('Received until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['received_from'], fn (Builder $query, $date): Builder => $query->whereDate('received_at', '>=', $date))
                            ->when($data['received_until'], fn (Builder $query, $date): Builder => $query->whereDate('received_at', '<=', $date));
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
