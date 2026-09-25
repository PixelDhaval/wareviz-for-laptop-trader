<?php

namespace App\Support\Reports;

use Illuminate\Support\Carbon;

/**
 * Resolves a report page's date-range filter (a preset, or a custom from/to
 * pair) into concrete bounds. `from`/`to` are null for "all_time" or an
 * unset custom bound, meaning "no lower/upper limit" — every report query
 * consuming this must ->when() around a null bound rather than assuming
 * both are always present.
 */
class ReportDateRange
{
    public function __construct(
        public readonly ?Carbon $from,
        public readonly ?Carbon $to,
        public readonly string $label,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function presetOptions(): array
    {
        return [
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_quarter' => 'This quarter',
            'this_year' => 'This year',
            'last_12_months' => 'Last 12 months',
            'all_time' => 'All time',
            'custom' => 'Custom range',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters  Raw page filter state: ['preset' => ..., 'from' => ..., 'to' => ...]
     */
    public static function fromFilters(array $filters): self
    {
        $preset = $filters['preset'] ?? null;
        $preset = (is_string($preset) && array_key_exists($preset, static::presetOptions())) ? $preset : 'this_month';

        if ($preset === 'custom') {
            $from = filled($filters['from'] ?? null) ? Carbon::parse($filters['from'])->startOfDay() : null;
            $to = filled($filters['to'] ?? null) ? Carbon::parse($filters['to'])->endOfDay() : null;

            return new self($from, $to, 'Custom range');
        }

        $now = Carbon::now();

        return match ($preset) {
            'this_month' => new self($now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'This month'),
            'last_month' => new self(
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'Last month',
            ),
            'this_quarter' => new self($now->copy()->startOfQuarter(), $now->copy()->endOfQuarter(), 'This quarter'),
            'this_year' => new self($now->copy()->startOfYear(), $now->copy()->endOfYear(), 'This year'),
            'last_12_months' => new self(
                $now->copy()->subMonthsNoOverflow(11)->startOfMonth(),
                $now->copy()->endOfMonth(),
                'Last 12 months',
            ),
            default => new self(null, null, 'All time'),
        };
    }

    /**
     * This range's bounds, with a null bound replaced by a sensible default
     * (the last 12 months) — for reports that must iterate a finite set of
     * months regardless of which preset is selected (e.g. "All time", which
     * otherwise has no bounds at all).
     *
     * @return array{Carbon, Carbon}
     */
    public function boundedForMonthlyReports(): array
    {
        return [
            $this->from ?? Carbon::now()->subMonthsNoOverflow(11)->startOfMonth(),
            $this->to ?? Carbon::now()->endOfMonth(),
        ];
    }
}
