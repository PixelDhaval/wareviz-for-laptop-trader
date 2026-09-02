<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LaptopStatus: string implements HasColor, HasLabel
{
    case InStock = 'in_stock';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Defective = 'defective';
    case InRepair = 'in_repair';

    public function getLabel(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::Reserved => 'Reserved',
            self::Sold => 'Sold',
            self::Defective => 'Defective',
            self::InRepair => 'In Repair',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::Reserved => 'warning',
            self::Sold => 'gray',
            self::Defective => 'danger',
            self::InRepair => 'info',
        };
    }
}
