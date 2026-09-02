<?php

namespace App\Filament\Resources\Processors\RelationManagers;

use App\Enums\LaptopStatus;
use App\Filament\Resources\Laptops\Schemas\LaptopForm;
use App\Filament\Resources\Laptops\Tables\LaptopsTable;
use App\Models\Laptop;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaptopsRelationManager extends RelationManager
{
    protected static string $relationship = 'laptops';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                LaptopForm::identificationSection(),
                Section::make('Specification')
                    ->columns(3)
                    ->schema(LaptopForm::specificationFields(includeProcessor: false)),
                Section::make('Condition checklist')
                    ->schema(LaptopForm::conditionFields()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('asset_code')
            ->columns([
                TextColumn::make('asset_code')
                    ->label('Serial Number')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('shipment.code')
                    ->label('Shipment')
                    ->searchable(),
                TextColumn::make('brand.name')
                    ->searchable(),
                TextColumn::make('laptopModel.name')
                    ->label('Model')
                    ->searchable(),
                TextColumn::make('generation.name')
                    ->label('Generation'),
                TextColumn::make('ram_gb')->label('RAM')->suffix(' GB'),
                TextColumn::make('storage_gb')->label('Storage')->suffix(' GB'),
                IconColumn::make('has_issues')
                    ->label('Issues')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('shipment_id')
                    ->relationship('shipment', 'code')
                    ->label('Shipment')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('brand_id')
                    ->relationship('brand', 'name')
                    ->label('Brand')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('laptop_model_id')
                    ->relationship('laptopModel', 'name')
                    ->label('Model')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('generation_id')
                    ->relationship('generation', 'name')
                    ->label('Generation')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('ram_gb')
                    ->label('RAM')
                    ->options(fn () => Laptop::query()
                        ->whereNotNull('ram_gb')
                        ->distinct()
                        ->orderBy('ram_gb')
                        ->pluck('ram_gb')
                        ->mapWithKeys(fn ($value) => [$value => "{$value} GB"]))
                    ->multiple(),
                SelectFilter::make('storage_gb')
                    ->label('Storage')
                    ->options(fn () => Laptop::query()
                        ->whereNotNull('storage_gb')
                        ->distinct()
                        ->orderBy('storage_gb')
                        ->pluck('storage_gb')
                        ->mapWithKeys(fn ($value) => [$value => "{$value} GB"]))
                    ->multiple(),
                SelectFilter::make('status')
                    ->options(LaptopStatus::class)
                    ->multiple(),
                TernaryFilter::make('has_issues'),
                TernaryFilter::make('has_builtin_ram')
                    ->label('Built-in memory'),
                Filter::make('created_at')
                    ->label('Imported')
                    ->schema([
                        DatePicker::make('imported_from')->label('Imported from'),
                        DatePicker::make('imported_until')->label('Imported until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['imported_from'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['imported_until'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    })
                    ->columnSpan(2),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                LaptopsTable::printBarcodeAction(),
                LaptopsTable::sendForJobAction(),
                LaptopsTable::completeJobAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                LaptopsTable::printBarcodesBulkAction(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
