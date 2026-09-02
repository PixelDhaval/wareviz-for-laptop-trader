<?php

namespace App\Filament\Resources\Laptops\Schemas;

use App\Models\Laptop;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class LaptopInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn (Laptop $record) => trim("{$record->brand?->name} {$record->laptopModel?->name}"))
                    ->description(fn (Laptop $record) => "Serial Number {$record->asset_code}")
                    ->icon(Heroicon::OutlinedComputerDesktop)
                    ->iconColor('primary')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('shipment.code')
                            ->label('Shipment'),
                        TextEntry::make('serial_no')
                            ->label('Packing list SN')
                            ->placeholder('—'),
                        TextEntry::make('has_issues')
                            ->label('Condition')
                            ->badge()
                            ->formatStateUsing(fn (bool $state) => $state ? 'Needs attention' : 'All OK')
                            ->color(fn (bool $state) => $state ? 'danger' : 'success'),

                        TextEntry::make('processor.name')
                            ->label('Processor'),
                        TextEntry::make('generation.name')
                            ->label('Generation')
                            ->placeholder('—'),
                        TextEntry::make('ram_gb')
                            ->label('RAM')
                            ->suffix(' GB')
                            ->placeholder('—'),
                        TextEntry::make('storage_gb')
                            ->label('Storage')
                            ->suffix(' GB')
                            ->placeholder('—'),

                        TextEntry::make('builtin_memory')
                            ->label('Built-in memory')
                            ->state(function (Laptop $record) {
                                if (! $record->has_builtin_ram) {
                                    return 'None';
                                }

                                return trim(implode(' / ', array_filter([
                                    $record->builtin_ram_gb ? "{$record->builtin_ram_gb} GB RAM" : null,
                                    $record->builtin_storage_gb ? "{$record->builtin_storage_gb} GB storage" : null,
                                ])));
                            })
                            ->columnSpan(2),
                        TextEntry::make('issues')
                            ->label('Issues / remarks')
                            ->placeholder('—')
                            ->color('danger')
                            ->columnSpan(2),
                    ]),

                Section::make('Condition checklist')
                    ->description('Each part as recorded on intake.')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                IconEntry::make('is_battery_ok')->label('Battery')->boolean(),
                                IconEntry::make('is_lcd_ok')->label('LCD')->boolean(),
                                IconEntry::make('is_bezel_ok')->label('Bezel')->boolean(),
                                IconEntry::make('is_top_cover_ok')->label('Top cover')->boolean(),
                                IconEntry::make('is_body_ok')->label('Body')->boolean(),
                                IconEntry::make('is_back_cover_ok')->label('Back cover')->boolean(),
                                IconEntry::make('is_keyboard_ok')->label('Keyboard')->boolean(),
                                IconEntry::make('is_touchpad_ok')->label('Touchpad')->boolean(),
                            ]),
                    ]),
            ]);
    }
}
