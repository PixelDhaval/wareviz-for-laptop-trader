<?php

namespace App\Models;

use Database\Factories\BuyerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The other party on a Sale — labeled "Buyer" for a Local sale and
 * "Consignee" for an Export sale (see App\Enums\SaleType), but one table:
 * both roles are the same kind of record, an external party you sell to.
 */
#[Fillable(['name', 'contact_person', 'phone', 'email', 'address'])]
class Buyer extends Model
{
    /** @use HasFactory<BuyerFactory> */
    use HasFactory;

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
