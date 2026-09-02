<?php

namespace App\Filament\Resources\Processors;

use App\Filament\Resources\Processors\Pages\CreateProcessor;
use App\Filament\Resources\Processors\Pages\EditProcessor;
use App\Filament\Resources\Processors\Pages\ListProcessors;
use App\Filament\Resources\Processors\RelationManagers\LaptopsRelationManager;
use App\Filament\Resources\Processors\Schemas\ProcessorForm;
use App\Filament\Resources\Processors\Tables\ProcessorsTable;
use App\Models\Processor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProcessorResource extends Resource
{
    protected static ?string $model = Processor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return ProcessorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LaptopsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcessors::route('/'),
            'create' => CreateProcessor::route('/create'),
            'edit' => EditProcessor::route('/{record}/edit'),
        ];
    }
}
