<?php

namespace App\Filament\Resources\Laptops\RelationManagers;

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RepairJobsRelationManager extends RelationManager
{
    protected static string $relationship = 'repairJobs';

    protected static ?string $title = 'Repair / Repaint Jobs';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ToggleButtons::make('type')
                    ->options(JobType::class)
                    ->grouped()
                    ->required(),
                ToggleButtons::make('assignee')
                    ->label('Assigned to')
                    ->options(JobAssignee::class)
                    ->grouped()
                    ->required()
                    ->live()
                    ->default(JobAssignee::InHouse),
                Select::make('agency_id')
                    ->label('Agency')
                    ->relationship('agency', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn ($get) => JobAssignee::resolve($get('assignee')) === JobAssignee::Agency)
                    ->visible(fn ($get) => JobAssignee::resolve($get('assignee')) === JobAssignee::Agency),
                TextInput::make('cost')
                    ->label('Expense')
                    ->numeric()
                    ->minValue(0),
                ToggleButtons::make('status')
                    ->options(JobStatus::class)
                    ->grouped()
                    ->default(JobStatus::Pending)
                    ->required()
                    ->live(),
                DatePicker::make('sent_at')
                    ->label('Sent on')
                    ->default(now()),
                DatePicker::make('completed_at')
                    ->label('Completed on')
                    ->visible(fn ($get) => JobStatus::resolve($get('status')) === JobStatus::Completed),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('assignee')
                    ->label('Assigned to')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('agency.name')
                    ->label('Agency')
                    ->placeholder('—'),
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
                TextColumn::make('notes')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sent_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(JobType::class)
                    ->multiple(),
                SelectFilter::make('assignee')
                    ->options(JobAssignee::class)
                    ->multiple(),
                SelectFilter::make('agency_id')
                    ->label('Agency')
                    ->relationship('agency', 'name')
                    ->searchable()
                    ->preload()
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
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
