<?php

namespace App\Filament\Resources\RepairJobs;

use App\Enums\JobStatus;
use App\Filament\Resources\RepairJobs\Pages\CreateRepairJob;
use App\Filament\Resources\RepairJobs\Pages\EditRepairJob;
use App\Filament\Resources\RepairJobs\Pages\ListRepairJobs;
use App\Filament\Resources\RepairJobs\Schemas\RepairJobForm;
use App\Filament\Resources\RepairJobs\Tables\RepairJobsTable;
use App\Models\RepairJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RepairJobResource extends Resource
{
    protected static ?string $model = RepairJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', [JobStatus::Pending->value, JobStatus::InProgress->value])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jobs currently pending or in progress';
    }

    public static function form(Schema $schema): Schema
    {
        return RepairJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RepairJobsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRepairJobs::route('/'),
            'create' => CreateRepairJob::route('/create'),
            'edit' => EditRepairJob::route('/{record}/edit'),
        ];
    }
}
