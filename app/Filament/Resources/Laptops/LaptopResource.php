<?php

namespace App\Filament\Resources\Laptops;

use App\Enums\LaptopStatus;
use App\Filament\Resources\Laptops\Pages\CreateLaptop;
use App\Filament\Resources\Laptops\Pages\EditLaptop;
use App\Filament\Resources\Laptops\Pages\ListLaptops;
use App\Filament\Resources\Laptops\Pages\ViewLaptop;
use App\Filament\Resources\Laptops\RelationManagers\RepairJobsRelationManager;
use App\Filament\Resources\Laptops\Schemas\LaptopForm;
use App\Filament\Resources\Laptops\Schemas\LaptopInfolist;
use App\Filament\Resources\Laptops\Tables\LaptopsTable;
use App\Models\Laptop;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class LaptopResource extends Resource
{
    protected static ?string $model = Laptop::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'asset_code';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', LaptopStatus::InStock)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Units currently in stock';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['asset_code', 'serial_no', 'brand.name', 'laptopModel.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Brand / Model' => trim("{$record->brand?->name} {$record->laptopModel?->name}"),
            'Status' => $record->status->getLabel(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return LaptopForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LaptopInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LaptopsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RepairJobsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaptops::route('/'),
            'create' => CreateLaptop::route('/create'),
            'view' => ViewLaptop::route('/{record}'),
            'edit' => EditLaptop::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
