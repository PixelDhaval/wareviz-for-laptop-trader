<?php

namespace App\Filament\Resources\Laptops\Pages;

use App\Filament\Resources\Laptops\LaptopResource;
use App\Filament\Resources\Laptops\Tables\LaptopsTable;
use BokshornIt\FilamentActivityTimeline\Actions\ActivityTimelineAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLaptop extends ViewRecord
{
    protected static string $resource = LaptopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActivityTimelineAction::make(),
            LaptopsTable::printBarcodeAction(),
            LaptopsTable::sendForJobAction(),
            LaptopsTable::completeJobAction(),
            EditAction::make(),
        ];
    }
}
