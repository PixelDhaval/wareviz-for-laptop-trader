<?php

namespace App\Filament\Resources\LaptopModels\Pages;

use App\Filament\Resources\LaptopModels\LaptopModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLaptopModel extends EditRecord
{
    protected static string $resource = LaptopModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
