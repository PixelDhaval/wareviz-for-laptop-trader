<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A per-type, per-period running counter backing App\Support\CodeGenerator.
 * period_key is the generated code's formatted date segment (e.g. "2609"),
 * or "" when that code has no date segment — in which case the counter
 * never resets and just keeps incrementing.
 */
#[Fillable(['type', 'period_key', 'last_number'])]
class CodeSequence extends Model
{
    public static function next(string $type, string $periodKey): int
    {
        static::query()->firstOrCreate(
            ['type' => $type, 'period_key' => $periodKey],
            ['last_number' => 0],
        );

        return DB::transaction(function () use ($type, $periodKey): int {
            $sequence = static::query()
                ->where('type', $type)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return $sequence->last_number;
        });
    }
}
