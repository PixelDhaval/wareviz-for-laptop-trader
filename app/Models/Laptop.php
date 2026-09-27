<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\LaptopStatus;
use App\Support\CodeGenerator;
use App\Support\Money;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\LaptopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'shipment_id',
    'brand_id',
    'laptop_model_id',
    'processor_id',
    'serial_no',
    'generation_id',
    'ram_gb',
    'storage_gb',
    'has_builtin_ram',
    'builtin_ram_gb',
    'builtin_storage_gb',
    'is_battery_ok',
    'is_lcd_ok',
    'is_bezel_ok',
    'is_top_cover_ok',
    'is_body_ok',
    'is_back_cover_ok',
    'is_keyboard_ok',
    'is_touchpad_ok',
    'issues',
    'status',
])]
class Laptop extends Model implements ProvidesActivityTitle
{
    /** @use HasFactory<LaptopFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function activityTitle(): ?string
    {
        return $this->asset_code;
    }

    protected static function booted(): void
    {
        static::creating(function (Laptop $laptop): void {
            $laptop->asset_code ??= (string) Str::ulid();
            $laptop->has_issues = filled($laptop->issues);
        });

        static::updating(function (Laptop $laptop): void {
            $laptop->has_issues = filled($laptop->issues);
        });

        static::created(function (Laptop $laptop): void {
            $laptop->forceFill([
                'asset_code' => CodeGenerator::forLaptop($laptop),
            ])->saveQuietly();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_builtin_ram' => 'boolean',
            'is_battery_ok' => 'boolean',
            'is_lcd_ok' => 'boolean',
            'is_bezel_ok' => 'boolean',
            'is_top_cover_ok' => 'boolean',
            'is_body_ok' => 'boolean',
            'is_back_cover_ok' => 'boolean',
            'is_keyboard_ok' => 'boolean',
            'is_touchpad_ok' => 'boolean',
            'has_issues' => 'boolean',
            'status' => LaptopStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<LaptopModel, $this>
     */
    public function laptopModel(): BelongsTo
    {
        return $this->belongsTo(LaptopModel::class);
    }

    /**
     * @return BelongsTo<Processor, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(Processor::class);
    }

    /**
     * @return BelongsTo<Generation, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /**
     * @return HasMany<RepairJob, $this>
     */
    public function repairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class)->latest('sent_at');
    }

    /**
     * @return HasOne<RepairJob, $this>
     */
    public function activeRepairJob(): HasOne
    {
        return $this->hasOne(RepairJob::class)
            ->whereIn('status', [JobStatus::Pending->value, JobStatus::InProgress->value])
            ->latestOfMany();
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * The sale this unit was actually sold on, if any. A laptop is only ever
     * expected to have one — SaleItem creation requires status InStock, so
     * once sold it can't be scanned into a second sale — but this reads the
     * latest in case a laptop was ever returned and resold.
     *
     * @return HasOne<SaleItem, $this>
     */
    public function saleItem(): HasOne
    {
        return $this->hasOne(SaleItem::class)->latestOfMany();
    }

    /**
     * What this unit cost to land, in the base currency: its shipment's
     * total cost spread evenly across the shipment's laptops (see
     * Shipment::averageCostPerLaptop()). Null while that can't be
     * determined — no shipment, or a shipment with no (non-trashed) laptops.
     *
     * @return Attribute<string|null, never>
     */
    protected function purchaseCost(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->shipment?->average_cost_per_laptop);
    }

    /**
     * Every repair/repaint job logged against this unit, converted to the
     * base currency and summed. Zero when it has none.
     *
     * @return Attribute<string, never>
     */
    protected function repairExpenseTotal(): Attribute
    {
        return Attribute::get(fn (): string => Money::roundToCents($this->exactRepairExpenseTotal()));
    }

    /**
     * Purchase cost plus every repair/repaint expense, in the base currency.
     * Null when the purchase cost can't be determined (see purchaseCost()).
     *
     * @return Attribute<string|null, never>
     */
    protected function totalCost(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->purchase_cost === null) {
                return null;
            }

            return Money::roundToCents(bcadd($this->purchase_cost, $this->exactRepairExpenseTotal(), 8));
        });
    }

    /**
     * What this unit actually sold for, in the base currency. Null while it
     * hasn't been sold.
     *
     * @return Attribute<string|null, never>
     */
    protected function salePrice(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->saleItem?->price_in_base_currency);
    }

    /**
     * Sale price minus total cost, in the base currency. Null while either
     * side can't be determined yet (not sold, or purchase cost unknown).
     *
     * @return Attribute<string|null, never>
     */
    protected function profit(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->sale_price === null || $this->total_cost === null) {
                return null;
            }

            return Money::roundToCents(bcsub($this->sale_price, $this->total_cost, 8));
        });
    }

    /**
     * The status this laptop should have based purely on its current sale
     * engagement: Sold once its sale is completed, Reserved while the sale
     * is still a draft, or InStock if it isn't on any sale at all. Used to
     * compute the right status whenever a sale item is added or removed, a
     * sale's completion is toggled, or a repair job finishes — see
     * SaleItem::booted(), Sale::booted() and RepairJob::syncLaptopStatus().
     * Never applied over Defective, which is set manually and independent
     * of sale state.
     */
    public function saleContextStatus(): LaptopStatus
    {
        $saleItem = $this->saleItem;

        if ($saleItem === null) {
            return LaptopStatus::InStock;
        }

        return $saleItem->sale->is_completed ? LaptopStatus::Sold : LaptopStatus::Reserved;
    }

    private function exactRepairExpenseTotal(): string
    {
        return $this->repairJobs->reduce(
            fn (string $total, RepairJob $job): string => bcadd($total, $job->cost_in_base_currency, 8),
            '0',
        );
    }
}
