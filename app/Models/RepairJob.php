<?php

namespace App\Models;

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\LaptopStatus;
use Database\Factories\RepairJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'laptop_id',
    'type',
    'assignee',
    'agency_id',
    'cost',
    'status',
    'sent_at',
    'completed_at',
    'notes',
])]
class RepairJob extends Model
{
    /** @use HasFactory<RepairJobFactory> */
    use HasFactory;

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
            $laptop->update(['status' => LaptopStatus::InStock]);
        }
    }
}
