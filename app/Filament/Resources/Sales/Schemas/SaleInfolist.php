<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Models\Currency;
use App\Models\Sale;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn (Sale $record) => $record->code)
                    ->description(fn (Sale $record) => $record->buyer?->name ?? 'No buyer on file')
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->iconColor('primary')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('type')
                            ->badge(),
                        TextEntry::make('buyer.name')
                            ->label('Buyer / Consignee')
                            ->placeholder('—'),
                        TextEntry::make('destination_country')
                            ->label('Destination')
                            ->placeholder('—'),
                        TextEntry::make('reference_no')
                            ->label('Reference no.')
                            ->placeholder('—'),
                        TextEntry::make('is_completed')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (bool $state) => $state ? 'Completed' : 'In progress')
                            ->color(fn (bool $state) => $state ? 'success' : 'warning'),

                        TextEntry::make('sold_at')
                            ->date(),
                        TextEntry::make('currency.code')
                            ->label('Currency'),
                        TextEntry::make('sale_items_count')
                            ->label('Laptops')
                            ->state(fn (Sale $record) => $record->saleItems()->count()),
                        TextEntry::make('total_sale_value')
                            ->label('Total value')
                            ->weight('bold')
                            ->numeric(decimalPlaces: 2)
                            ->prefix(fn (): string => Money::currencyPrefix(Currency::base())),

                        TextEntry::make('notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
