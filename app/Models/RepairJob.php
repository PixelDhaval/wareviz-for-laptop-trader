<?php

namespace App\Models;

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\LaptopStatus;
use App\Support\Money;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\RepairJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'laptop_id',
    'type',
    'assignee',
    'agency_id',
    'cost',
    'cost_currency_id',
    'cost_exchange_rate',
    'status',
    'sent_at',
    'completed_at',
    'notes',
])]
class RepairJob extends Model implements ProvidesActivityTitle
{
    /** @use HasFactory<RepairJobFactory> */
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
        if (! $this->laptop_id) {
            return null;
        }

        return "{$this->laptop?->asset_code} ({$this->type?->getLabel()})";
    }

    protected static function booted(): void
    {
        static::creating(function (RepairJob $job): void {
            $job->status ??= JobStatus::Pending;
            $job->sent_at ??= now()->toDateString();
        });

        static::saving(function (RepairJob $job): void {
            if ($job->assignee !== JobAssignee::Agency) {
                $job->agency_id = null;
            }

            if ($job->status === JobStatus::Completed && ! $job->completed_at) {
                $job->completed_at = now()->toDateString();
            }
        });

        static::saved(function (RepairJob $job): void {
            $job->syncLaptopStatus();
        });

        static::created(function (RepairJob $job): void {
            // cost_exchange_rate defaults to 1 at the DB level when not given
            // explicitly. Without a refresh, the in-memory instance keeps it
            // null, so the first later update() logs a phantom "changed from
            // null" (activitylog's logOnlyDirty() diffs against this
            // instance's stale original) — see Shipment::booted() for the
            // same fix.
            $job->refresh();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => JobType::class,
            'assignee' => JobAssignee::class,
            'status' => JobStatus::class,
            'cost' => 'decimal:2',
            'cost_exchange_rate' => 'decimal:6',
            'sent_at' => 'date',
            'completed_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Laptop, $this>
     */
    public function laptop(): BelongsTo
    {
        return $this->belongsTo(Laptop::class);
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'cost_currency_id');
    }

    /**
     * This job's expense converted to the base currency at its own stored
     * exchange rate — a per-job snapshot, same as a shipment's cost lines
     * (see App\Models\Shipment::costInBaseCurrency()). Zero when the job has
     * no expense.
     *
     * @return Attribute<string, never>
     */
    protected function costInBaseCurrency(): Attribute
    {
        return Attribute::get(fn (): string => Money::convert($this->cost, $this->cost_exchange_rate));
    }

    protected function syncLaptopStatus(): void
    {
        $laptop = $this->laptop;

        if (! $laptop) {
            return;
        }

        if ($this->status->isActive()) {
            if ($laptop->status !== LaptopStatus::InRepair) {
                $laptop->update(['status' => LaptopStatus::InRepair]);
            }

            return;
        }

        if ($laptop->status === LaptopStatus::InRepair) {
            $laptop->update(['status' => $laptop->saleContextStatus()]);
        }
    }
}
