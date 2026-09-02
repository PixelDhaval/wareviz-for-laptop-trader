<?php

namespace App\Filament\Resources\Brands\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaptopModelsRelationManager extends RelationManager
{
    protected static string $relationship = 'laptopModels';

    protected static ?string $title = 'Models';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule) => $rule->where('brand_id', $this->getOwnerRecord()->id),
                    ),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('laptops_count')
                    ->label('Laptops in stock')
                    ->counts('laptops'),
            ])
            ->filters([
                TernaryFilter::make('has_laptops')
                    ->label('Has laptops')
                    ->placeholder('All models')
                    ->trueLabel('Has laptops in stock')
                    ->falseLabel('No laptops yet')
                    ->queries(
                        true: fn (Builder $query) => $query->has('laptops'),
                        false: fn (Builder $query) => $query->doesntHave('laptops'),
                        blank: fn (Builder $query) => $query,
                    ),
            ], layout: FiltersLayout::AboveContentCollapsible)
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
