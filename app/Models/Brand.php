<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    /**
     * @return HasMany<LaptopModel, $this>
     */
    public function laptopModels(): HasMany
    {
        return $this->hasMany(LaptopModel::class);
    }

    /**
     * @return HasMany<Laptop, $this>
     */
    public function laptops(): HasMany
    {
        return $this->hasMany(Laptop::class);
    }
}
