<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditSale extends EditRecord
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
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
