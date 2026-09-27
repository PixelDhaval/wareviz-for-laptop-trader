<?php

namespace App\Models;

use App\Enums\CodeDateFormat;
use App\Enums\CodeDateSource;
use App\Enums\CodeSegmentPosition;
use App\Enums\SaleType;
use App\Enums\ShipmentCostType;
use App\Enums\ShipmentType;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A singleton row of application-wide defaults: which currency to preselect
 * on the Sale form (per SaleType) and on the Shipment form's expense lines
 * (per ShipmentCostType) — purely a UI convenience, never consulted again
 * once a record is saved with its own currency snapshot — plus the format
 * used to auto-generate Laptop asset codes and Sale codes (see
 * App\Support\CodeGenerator).
 */
#[Fillable([
    'sale_local_currency_id',
    'sale_export_currency_id',
    'invoice_value_currency_id',
    'freight_cost_currency_id',
    'local_expense_currency_id',
    'duty_currency_id',
    'other_expense_currency_id',
    'local_purchase_currency_id',
    'repair_cost_currency_id',
    'fiscal_year_start_month',
    'laptop_code_prefix',
    'laptop_code_suffix',
    'laptop_code_separator',
    'laptop_code_date_source',
    'laptop_code_date_format',
    'laptop_code_sequence_pad',
    'sale_code_prefix',
    'sale_code_suffix',
    'sale_code_separator',
    'sale_code_date_format',
    'sale_code_date_position',
    'sale_code_sequence_pad',
])]
class Setting extends Model implements ProvidesActivityTitle
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityTitle(): ?string
    {
        return 'Application settings';
    }

    /**
     * firstOrCreate() on a brand-new row leaves the in-memory instance
     * carrying nulls for every column that was populated by a DB-level
     * default (e.g. laptop_code_prefix) rather than an explicit value —
     * Eloquent never re-reads those back after INSERT. refresh() re-fetches
     * so callers always see the real, defaulted values, not nulls.
     */
    public static function current(): self
    {
        $setting = static::query()->firstOrCreate([]);

        return $setting->wasRecentlyCreated ? $setting->refresh() : $setting;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'laptop_code_date_source' => CodeDateSource::class,
            'laptop_code_date_format' => CodeDateFormat::class,
            'sale_code_date_format' => CodeDateFormat::class,
            'sale_code_date_position' => CodeSegmentPosition::class,
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function saleLocalCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function saleExportCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
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
     * @return BelongsTo<Currency, $this>
     */
    public function localPurchaseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function repairCostCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * The default currency id to preselect on the Sale form for this sale
     * type, or null if none is configured.
     */
    public function saleCurrencyId(SaleType $type): ?int
    {
        return match ($type) {
            SaleType::Local => $this->sale_local_currency_id,
            SaleType::Export => $this->sale_export_currency_id,
        };
    }

    /**
     * The default currency id to preselect on the Shipment form for this
     * cost line, or null if none is configured. A local purchase uses one
     * single currency (`local_purchase_currency_id`) for every cost line,
     * instead of each line's own per-type setting.
     */
    public function shipmentCostCurrencyId(ShipmentCostType $type, ?ShipmentType $shipmentType = null): ?int
    {
        if ($shipmentType === ShipmentType::Local) {
            return $this->local_purchase_currency_id;
        }

        return $this->{$type->currencyColumn()};
    }
}
