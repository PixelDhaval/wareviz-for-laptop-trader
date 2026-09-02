<?php

namespace App\Filament\Resources\Processors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProcessorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
            ]);
    }
}
