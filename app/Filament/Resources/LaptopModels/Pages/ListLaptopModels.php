<?php

namespace App\Filament\Resources\LaptopModels\Pages;

use App\Filament\Resources\LaptopModels\LaptopModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLaptopModels extends ListRecords
{
    protected static string $resource = LaptopModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
