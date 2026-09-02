<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JobAssignee: string implements HasLabel
{
    case InHouse = 'in_house';
    case Agency = 'agency';

    public function getLabel(): string
    {
        return match ($this) {
            self::InHouse => 'In-house',
            self::Agency => 'Other Agency',
        };
    }

    /**
     * Safely resolve a value that may already be a JobAssignee instance, or its
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
