<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Models\Laptop;
use Closure;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

class LaptopDetailsPreview
{
    /**
     * A compact read-only grid of a laptop's key specs, for the moment
     * between picking a laptop and entering its price — the scan modal on
     * ScanSale, and the Laptops relation manager's "Add laptop" form. Built
     * from `Filament\Infolists\Components\TextEntry` used as plain schema
     * components (the same technique as ShipmentForm's cost summary), since
     * neither context has a persisted SaleItem record to bind an infolist to.
     *
     * @param  Closure(Get $get): ?Laptop  $resolveLaptop  Filament injects
     *                                                     `Get $get` into this closure like any other schema closure; a caller
     *                                                     with no form field to read from (the scan modal, where the laptop is
     *                                                     already a fixed PHP value) can simply ignore the parameter.
     */
    public static function grid(Closure $resolveLaptop): Grid
    {
        return Grid::make(3)
            ->schema([
                TextEntry::make('laptop_preview_brand')
                    ->label('Brand / Model')
                    ->state(fn (Get $get) => trim("{$resolveLaptop($get)?->brand?->name} {$resolveLaptop($get)?->laptopModel?->name}")),
                TextEntry::make('laptop_preview_processor')
                    ->label('Processor')
                    ->state(fn (Get $get) => trim("{$resolveLaptop($get)?->processor?->name} {$resolveLaptop($get)?->generation?->name}")),
                TextEntry::make('laptop_preview_memory')
                    ->label('RAM / Storage')
                    ->state(fn (Get $get) => ($resolveLaptop($get)?->ram_gb ?? '—').' GB / '.($resolveLaptop($get)?->storage_gb ?? '—').' GB'),
                TextEntry::make('laptop_preview_condition')
                    ->label('Condition')
                    ->badge()
                    ->columnSpanFull()
                    ->state(fn (Get $get) => $resolveLaptop($get)?->has_issues ? 'Needs attention' : 'All OK')
                    ->color(fn (Get $get) => $resolveLaptop($get)?->has_issues ? 'danger' : 'success'),
            ]);
    }
}
