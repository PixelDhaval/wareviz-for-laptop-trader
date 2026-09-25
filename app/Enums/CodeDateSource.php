<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which date a generated Laptop asset code's date segment is drawn from.
 */
enum CodeDateSource: string implements HasLabel
{
    case ShipmentReceivedAt = 'shipment_received_at';
    case CreatedAt = 'created_at';

    public function getLabel(): string
    {
        return match ($this) {
            self::ShipmentReceivedAt => "Shipment's received date",
            self::CreatedAt => 'Date the laptop record was created',
        };
    }
}
