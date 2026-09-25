<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    /**
     * Straight into scan mode — the natural next step after setting up a
     * sale is to start scanning laptops into it.
     */
    protected function getRedirectUrl(): string
    {
        return SaleResource::getUrl('scan', ['record' => $this->getRecord()]);
    }
}
