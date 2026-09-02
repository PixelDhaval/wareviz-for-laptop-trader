<?php

namespace App\Filament\Resources\Processors\Pages;

use App\Filament\Resources\Processors\ProcessorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcessors extends ListRecords
{
    protected static string $resource = ProcessorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
