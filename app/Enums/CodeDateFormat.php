<?php

namespace App\Enums;

use App\Support\FiscalYear;
use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

/**
 * The date segment format for a generated Laptop asset code or Sale code.
 * Fiscal-year variants are computed via App\Support\FiscalYear using
 * Setting::current()->fiscal_year_start_month (calendar year if unset).
 */
enum CodeDateFormat: string implements HasLabel
{
    case Year4 = 'Y';
    case Year2 = 'y';
    case YearMonth = 'ym';
    case MonthYear = 'my';
    case YearMonthDay = 'ymd';
    case FiscalYearShort = 'fy_short';
    case FiscalYearLong = 'fy_long';

    public function getLabel(): string
    {
        return match ($this) {
            self::Year4 => 'Year (2026)',
            self::Year2 => 'Year, 2 digits (26)',
            self::YearMonth => 'Year + month (2609)',
            self::MonthYear => 'Month + year (0926)',
            self::YearMonthDay => 'Year + month + day (260315)',
            self::FiscalYearShort => 'Fiscal year, short (2526)',
            self::FiscalYearLong => 'Fiscal year (25-26)',
        };
    }

    public function format(CarbonInterface $date, ?int $fiscalYearStartMonth): string
    {
        return match ($this) {
            self::Year4, self::Year2, self::YearMonth, self::MonthYear, self::YearMonthDay => $date->format($this->value),
            self::FiscalYearShort => FiscalYear::label($date, $fiscalYearStartMonth, short: true),
            self::FiscalYearLong => FiscalYear::label($date, $fiscalYearStartMonth, short: false),
        };
    }
}
