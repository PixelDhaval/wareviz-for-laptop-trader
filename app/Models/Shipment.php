<?php

namespace App\Models;

use App\Enums\ShipmentCostType;
use App\Enums\ShipmentType;
use App\Support\Money;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'code',
    'type',
    'supplier_id',
    'name',
    'received_at',
    'invoice_date',
    'is_completed',
    'notes',
    'invoice_value',
    'invoice_value_currency_id',
    'invoice_value_exchange_rate',
    'freight_cost',
    'freight_cost_currency_id',
    'freight_cost_exchange_rate',
    'local_expense',
    'local_expense_currency_id',
    'local_expense_exchange_rate',
    'duty',
    'duty_currency_id',
    'duty_exchange_rate',
    'other_expense',
    'other_expense_currency_id',
    'other_expense_exchange_rate',
])]
class Shipment extends Model implements ProvidesActivityTitle
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        static::created(function (Shipment $shipment): void {
            // The cost columns default at the DB level (0 / exchange rate 1)
            // when not given explicitly. Without a refresh, the in-memory
            // instance keeps them null, so the first later update() logs a
            // phantom "changed from null" for every one of them (activitylog's
            // logOnlyDirty() diffs against this instance's stale original).
            $shipment->refresh();
        });
    }

    public function activityTitle(): ?string
    {
        return $this->code;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'type' => ShipmentType::class,
            'received_at' => 'date',
            'invoice_date' => 'date',
            'is_completed' => 'boolean',
        ];

        foreach (ShipmentCostType::cases() as $type) {
            $casts[$type->value] = 'decimal:2';
            $casts[$type->exchangeRateColumn()] = 'decimal:6';
        }

        return $casts;
    }

    /**
     * @return HasMany<Laptop, $this>
     */
    public function laptops(): HasMany
    {
        return $this->hasMany(Laptop::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function invoiceValueCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, ShipmentCostType::InvoiceValue->currencyColumn());
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function freightCostCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, ShipmentCostType::FreightCost->currencyColumn());
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function localExpenseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, ShipmentCostType::LocalExpense->currencyColumn());
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function dutyCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, ShipmentCostType::Duty->currencyColumn());
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function otherExpenseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, ShipmentCostType::OtherExpense->currencyColumn());
    }

    /**
     * One cost line converted to the base currency at this shipment's stored
     * exchange rate, as a decimal string with 8 decimal places.
     */
    public function costInBaseCurrency(ShipmentCostType $type): string
    {
        return Money::convert($this->{$type->value}, $this->{$type->exchangeRateColumn()});
    }

    /**
     * Landed cost of the shipment in the base currency: invoice value plus
     * freight, local expense, duty and other expense.
     *
     * @return Attribute<string, never>
     */
    protected function totalCost(): Attribute
    {
        return Attribute::get(fn (): string => Money::roundToCents($this->exactTotalCost()));
    }

    /**
     * Total cost divided across the shipment's laptops (soft-deleted units are
     * not counted), in the base currency. Null while the shipment has no
     * laptops. Uses `laptops_count` when it has been loaded to skip a query.
     *
     * @return Attribute<string|null, never>
     */
    protected function averageCostPerLaptop(): Attribute
    {
        return Attribute::get(function (): ?string {
            $laptopCount = (int) ($this->laptops_count ?? $this->laptops()->count());

            if ($laptopCount === 0) {
                return null;
            }

            return Money::roundToCents(bcdiv($this->exactTotalCost(), (string) $laptopCount, 8));
        });
    }

    private function exactTotalCost(): string
    {
        return array_reduce(
            ShipmentCostType::cases(),
            fn (string $total, ShipmentCostType $type): string => bcadd($total, $this->costInBaseCurrency($type), 8),
            '0',
        );
    }
}
