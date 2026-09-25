<?php

namespace App\Filament\Resources\Generations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GenerationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->dense()
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
            ]);
    }
}
