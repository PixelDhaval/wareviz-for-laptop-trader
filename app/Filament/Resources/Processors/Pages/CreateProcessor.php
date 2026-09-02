<?php

namespace App\Filament\Resources\Processors\Pages;

use App\Filament\Resources\Processors\ProcessorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProcessor extends CreateRecord
{
    protected static string $resource = ProcessorResource::class;
}
