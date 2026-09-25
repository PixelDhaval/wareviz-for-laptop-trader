<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * A fiscal year label such as "25-26" (short form "2526"), computed from a
 * date and the fiscal year's start month. Falls back to the calendar year
 * (start month 1) when no start month is configured.
 */
class FiscalYear
{
    public static function label(CarbonInterface $date, ?int $startMonth, bool $short): string
    {
        $startMonth ??= 1;

        $startYear = $date->month >= $startMonth ? $date->year : $date->year - 1;
        $endYear = $startYear + 1;

        $startShort = substr((string) $startYear, -2);
        $endShort = substr((string) $endYear, -2);

        return $short ? "{$startShort}{$endShort}" : "{$startShort}-{$endShort}";
    }
}
