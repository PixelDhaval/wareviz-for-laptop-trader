<?php

namespace App\Filament\Resources\Currencies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CurrencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->dense()
            ->components([
                TextInput::make('code')
                    ->label('ISO code')
                    ->required()
                    ->length(3)
                    ->regex('/^[A-Z]{3}$/')
                    ->validationMessages(['regex' => 'Use a 3-letter uppercase ISO 4217 code, e.g. USD.'])
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required(),
                TextInput::make('symbol')
                    ->maxLength(8),
                TextInput::make('exchange_rate')
                    ->label('Exchange rate to base')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('How much 1 unit of this currency is worth in the base currency. Prefills new shipments; each shipment keeps its own copy.'),
                Toggle::make('is_base')
                    ->label('Base currency')
                    ->helperText('All shipment costs are converted into this currency. Only one currency can be the base; turning this on clears it from the previous one and fixes the rate at 1.')
                    ->columnSpanFull(),
            ]);
    }
}
