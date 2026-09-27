<?php

namespace App\Models;

use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\BuyerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The other party on a Sale — labeled "Buyer" for a Local sale and
 * "Consignee" for an Export sale (see App\Enums\SaleType), but one table:
 * both roles are the same kind of record, an external party you sell to.
 */
#[Fillable(['name', 'contact_person', 'phone', 'email', 'address'])]
class Buyer extends Model implements ProvidesActivityTitle
{
    /** @use HasFactory<BuyerFactory> */
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
        return $this->name;
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
