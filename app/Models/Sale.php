<?php

namespace App\Models;

use App\Enums\LaptopStatus;
use App\Enums\SaleType;
use App\Support\CodeGenerator;
use App\Support\Money;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'type',
    'buyer_id',
    'destination_country',
    'reference_no',
    'currency_id',
    'exchange_rate',
    'sold_at',
    'is_completed',
    'notes',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Sale $sale): void {
            $sale->sold_at ??= now()->toDateString();
            $sale->code ??= CodeGenerator::forSale($sale);
        });

        static::deleting(function (Sale $sale): void {
            // Delete each item individually rather than relying on the
            // sale_items.sale_id FK cascade, so SaleItem::deleted() fires for
            // every row and reverts each laptop's status back to in stock.
            $sale->saleItems->each->delete();
        });

        static::updated(function (Sale $sale): void {
            if (! $sale->wasChanged('is_completed')) {
                return;
            }

            // Draft -> completed moves every laptop still Reserved on this
            // sale to Sold; completed -> draft (re-opening) moves them back.
            // Only laptops currently Sold/Reserved are touched — one mid
            // repair (InRepair) or marked Defective in the meantime is left
            // alone; RepairJob::syncLaptopStatus() will apply the correct
            // status via Laptop::saleContextStatus() once that job finishes.
            $targetStatus = $sale->is_completed ? LaptopStatus::Sold : LaptopStatus::Reserved;

            $sale->saleItems()
                ->with('laptop')
                ->get()
                ->each(function (SaleItem $item) use ($targetStatus): void {
                    $laptop = $item->laptop;

                    if ($laptop && in_array($laptop->status, [LaptopStatus::Sold, LaptopStatus::Reserved], true)) {
                        $laptop->update(['status' => $targetStatus]);
                    }
                });
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SaleType::class,
            'exchange_rate' => 'decimal:6',
            'sold_at' => 'date',
            'is_completed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * The other party on this sale — a Buyer for Local, a Consignee for
     * Export (see App\Models\Buyer).
     *
     * @return BelongsTo<Buyer, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Every laptop's sale price on this sale, converted to the base currency
     * at each item's own exchange rate, and summed. Zero when the sale has no
     * items yet.
     *
     * @return Attribute<string, never>
     */
    protected function totalSaleValue(): Attribute
    {
        return Attribute::get(fn (): string => Money::roundToCents($this->exactTotalSaleValueInBaseCurrency()));
    }

    /**
     * The same total as total_sale_value, converted back into this sale's
     * own currency at its own stored exchange rate — what the sale actually
     * bills in, as opposed to total_sale_value's base-currency figure (used
     * for cross-sale reporting, e.g. the Sales list and infolist).
     */
    public function totalSaleValueInOwnCurrency(): string
    {
        return Money::roundToCents(
            Money::convertFromBase($this->exactTotalSaleValueInBaseCurrency(), $this->exchange_rate),
        );
    }

    private function exactTotalSaleValueInBaseCurrency(): string
    {
        return $this->saleItems->reduce(
            fn (string $total, SaleItem $item): string => bcadd($total, $item->price_in_base_currency, 8),
            '0',
        );
    }

    /**
     * Sales matching a search term against the sale code or the buyer's
     * name, keyed by id => a label with enough detail (code, buyer,
     * type, date) to pick the right one. Backs LaptopsTable's "Add to
     * sale" select: a search-as-you-type query rather than a full
     * ->options() list, since loading every sale up front doesn't scale.
     *
     * @return array<int, string>
     */
    public static function searchableOptions(string $search): array
    {
        if ($search == '' || $search == null) {
            return static::query()
                ->with('buyer')
                ->latest('sold_at')
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (Sale $sale): array => [$sale->id => $sale->optionLabel()])
                ->all();
        }

        return static::query()
            ->with('buyer')
            ->where(fn (Builder $query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhereHas('buyer', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))
            ->latest('sold_at')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Sale $sale): array => [$sale->id => $sale->optionLabel()])
            ->all();
    }

    public function optionLabel(): string
    {
        return sprintf(
            '%s — %s (%s, %s)',
            $this->code,
            $this->buyer?->name ?? 'No buyer',
            $this->type->getLabel(),
            $this->sold_at?->format('d M Y') ?? '—',
        );
    }
}
