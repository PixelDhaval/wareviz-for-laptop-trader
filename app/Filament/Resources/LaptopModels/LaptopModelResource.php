<?php

namespace App\Filament\Resources\LaptopModels;

use App\Filament\Resources\LaptopModels\Pages\CreateLaptopModel;
use App\Filament\Resources\LaptopModels\Pages\EditLaptopModel;
use App\Filament\Resources\LaptopModels\Pages\ListLaptopModels;
use App\Filament\Resources\LaptopModels\RelationManagers\LaptopsRelationManager;
use App\Filament\Resources\LaptopModels\Schemas\LaptopModelForm;
use App\Filament\Resources\LaptopModels\Tables\LaptopModelsTable;
use App\Models\LaptopModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LaptopModelResource extends Resource
{
    protected static ?string $model = LaptopModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return LaptopModelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LaptopModelsTable::configure($table);
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
            'index' => ListLaptopModels::route('/'),
            'create' => CreateLaptopModel::route('/create'),
            'edit' => EditLaptopModel::route('/{record}/edit'),
        ];
    }
}
