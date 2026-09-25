<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('Scan laptops')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('warning')
                ->url(fn () => SaleResource::getUrl('scan', ['record' => $this->getRecord()])),
            EditAction::make(),
        ];
    }
}
