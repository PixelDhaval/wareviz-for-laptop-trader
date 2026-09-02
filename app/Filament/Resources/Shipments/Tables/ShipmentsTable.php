<?php

namespace App\Filament\Resources\Shipments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShipmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('received_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('laptops_count')
                    ->label('Laptops')
                    ->counts('laptops')
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
            ->filters([
                TernaryFilter::make('is_completed'),
                TernaryFilter::make('has_laptops')
                    ->label('Has laptops')
                    ->placeholder('All shipments')
                    ->trueLabel('Has laptops imported')
                    ->falseLabel('No laptops yet')
                    ->queries(
                        true: fn (Builder $query) => $query->has('laptops'),
                        false: fn (Builder $query) => $query->doesntHave('laptops'),
                        blank: fn (Builder $query) => $query,
                    ),
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
                    ->columnSpan(2)
                    ->columns(2),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
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
