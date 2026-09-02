<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\LaptopStatus;
use Database\Factories\LaptopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
class Laptop extends Model
{
    /** @use HasFactory<LaptopFactory> */
    use HasFactory, SoftDeletes;

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
                'asset_code' => sprintf('WV%06d', $laptop->id),
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
}
