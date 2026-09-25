<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'symbol', 'exchange_rate', 'is_base'])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Currency $currency): void {
            if ($currency->is_base) {
                $currency->exchange_rate = 1;
            }
        });

        static::saved(function (Currency $currency): void {
            if ($currency->is_base) {
                static::query()
                    ->whereKeyNot($currency->getKey())
                    ->where('is_base', true)
                    ->update(['is_base' => false]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
            'is_base' => 'boolean',
        ];
    }

    /**
     * The currency every shipment cost is converted into, if one is set.
     */
    public static function base(): ?self
    {
        return static::query()->where('is_base', true)->first();
    }
}
