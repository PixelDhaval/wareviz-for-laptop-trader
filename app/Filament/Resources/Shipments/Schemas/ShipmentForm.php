<?php

namespace App\Filament\Resources\Shipments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Shipment / container code')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('name'),
                DatePicker::make('received_at')
                    ->default(now()),
                Toggle::make('is_completed')
                    ->helperText('Mark as completed once all units from the packing list have been imported.'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
