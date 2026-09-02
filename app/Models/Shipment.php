<?php

namespace App\Models;

use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'received_at', 'is_completed', 'notes'])]
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'is_completed' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Laptop, $this>
     */
    public function laptops(): HasMany
    {
        return $this->hasMany(Laptop::class);
    }
}
