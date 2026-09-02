<?php

namespace App\Filament\Resources\Processors\Pages;

use App\Filament\Resources\Processors\ProcessorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessor extends EditRecord
{
    protected static string $resource = ProcessorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
