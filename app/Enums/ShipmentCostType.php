<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum ShipmentCostType: string implements HasLabel
{
    case InvoiceValue = 'invoice_value';
    case FreightCost = 'freight_cost';
    case LocalExpense = 'local_expense';
    case Duty = 'duty';
    case OtherExpense = 'other_expense';

    public function getLabel(): string
    {
        return match ($this) {
            self::InvoiceValue => 'Invoice value',
            self::FreightCost => 'Freight cost',
            self::LocalExpense => 'Local expense',
            self::Duty => 'Duty',
            self::OtherExpense => 'Other expense',
        };
    }

    /**
     * Column holding the amount is the enum value itself; these two derive the
     * companion columns on the shipments table.
     */
    public function currencyColumn(): string
    {
        return "{$this->value}_currency_id";
    }

    public function exchangeRateColumn(): string
    {
        return "{$this->value}_exchange_rate";
    }

    /**
     * Name of the Shipment BelongsTo relationship for this cost's currency.
     */
    public function currencyRelationship(): string
    {
        return Str::camel($this->value).'Currency';
    }
}
