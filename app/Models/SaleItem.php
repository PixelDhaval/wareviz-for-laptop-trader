<?php

namespace App\Models;

use App\Enums\LaptopStatus;
use App\Support\Money;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_id',
    'laptop_id',
    'price',
    'price_currency_id',
    'price_exchange_rate',
])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (SaleItem $item): void {
            $laptop = $item->laptop()->first();

            $laptop?->update([
                'status' => $item->sale->is_completed ? LaptopStatus::Sold : LaptopStatus::Reserved,
            ]);
        });

        static::deleted(function (SaleItem $item): void {
            // Query fresh rather than the `laptop` accessor: if this $item
            // instance's relation was already cached (e.g. by the created()
            // hook above, on an $item that's deleted later in the same
            // request), the cached copy would miss a status change made
            // through a different instance in between and revert wrongly.
            $laptop = $item->laptop()->first();

            if ($laptop && in_array($laptop->status, [LaptopStatus::Sold, LaptopStatus::Reserved], true)) {
                $laptop->update(['status' => LaptopStatus::InStock]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_exchange_rate' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Laptop, $this>
     */
    public function laptop(): BelongsTo
    {
        return $this->belongsTo(Laptop::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'price_currency_id');
    }

    /**
     * This laptop's sale price converted to the base currency at this item's
     * own stored exchange rate — a per-item snapshot, same as a shipment's
     * cost lines and a repair job's expense.
     *
     * @return Attribute<string, never>
     */
    protected function priceInBaseCurrency(): Attribute
    {
        return Attribute::get(fn (): string => Money::convert($this->price, $this->price_exchange_rate));
    }
}
