<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SaleType: string implements HasLabel
{
    case Local = 'local';
    case Export = 'export';

    public function getLabel(): string
    {
        return match ($this) {
            self::Local => 'Local',
            self::Export => 'Export',
        };
    }

    /**
     * Safely resolve a value that may already be a SaleType instance, or its
     * raw string form (as Filament form state sometimes provides either).
     */
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
