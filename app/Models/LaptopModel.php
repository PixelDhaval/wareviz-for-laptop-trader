<?php

namespace App\Models;

use Database\Factories\LaptopModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['brand_id', 'name'])]
class LaptopModel extends Model
{
    /** @use HasFactory<LaptopModelFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<Laptop, $this>
     */
    public function laptops(): HasMany
    {
        return $this->hasMany(Laptop::class);
    }
}
