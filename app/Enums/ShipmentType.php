<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ShipmentType: string implements HasLabel
{
    case Local = 'local';
    case Import = 'import';

    public function getLabel(): string
    {
        return match ($this) {
            self::Local => 'Local',
            self::Import => 'Import',
        };
    }

    public static function resolve(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return self::tryFrom($value);
        }

        return null;
    }
}
