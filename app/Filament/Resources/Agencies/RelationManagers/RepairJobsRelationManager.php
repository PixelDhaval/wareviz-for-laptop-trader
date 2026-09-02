<?php

namespace App\Filament\Resources\Agencies\RelationManagers;

use App\Enums\JobStatus;
use App\Enums\JobType;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RepairJobsRelationManager extends RelationManager
{
    protected static string $relationship = 'repairJobs';

    protected static ?string $title = 'Jobs';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('laptop.asset_code')
                    ->label('Laptop')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('cost')
                    ->label('Expense')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('sent_at')
                    ->date(),
                TextColumn::make('completed_at')
                    ->date()
                    ->placeholder('—'),
            ])
            ->defaultSort('sent_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(JobType::class)
                    ->multiple(),
                SelectFilter::make('status')
                    ->options(JobStatus::class)
                    ->multiple(),
                Filter::make('cost')
                    ->schema([
                        TextInput::make('cost_from')->label('Expense from')->numeric()->minValue(0),
                        TextInput::make('cost_until')->label('Expense until')->numeric()->minValue(0),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['cost_from'], fn (Builder $query, $cost): Builder => $query->where('cost', '>=', $cost))
                            ->when($data['cost_until'], fn (Builder $query, $cost): Builder => $query->where('cost', '<=', $cost));
                    })
                    ->columnSpan(2),
                Filter::make('sent_at')
                    ->schema([
                        DatePicker::make('sent_from')->label('Sent from'),
                        DatePicker::make('sent_until')->label('Sent until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['sent_from'], fn (Builder $query, $date): Builder => $query->whereDate('sent_at', '>=', $date))
                            ->when($data['sent_until'], fn (Builder $query, $date): Builder => $query->whereDate('sent_at', '<=', $date));
                    })
                    ->columnSpan(2),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->deferFilters(false)
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
