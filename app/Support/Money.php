<?php

namespace App\Support;

use App\Models\Currency;

/**
 * Shared bcmath helpers for currency conversion. Values are always decimal
 * strings; floats lose precision on money, so this never touches them until
 * a caller casts the final, rounded result for display.
 */
class Money
{
    /**
     * Converts an amount to another currency (or a shipment/job's base
     * currency) at the given exchange rate, keeping `$scale` decimal places
     * of intermediate precision. A missing amount or rate is treated as 0 and
     * 1 respectively, matching each cost line's database defaults.
     */
    public static function convert(string|int|float|null $amount, string|int|float|null $rate, int $scale = 8): string
    {
        return bcmul((string) ($amount ?? 0), (string) ($rate ?? 1), $scale);
    }

    /**
     * The inverse of convert(): given an amount already in the base
     * currency, returns how much that is in the currency whose rate this is
     * (rate meaning, as everywhere else, "1 unit of this currency = $rate
     * units of the base currency"). A missing or zero rate is treated as 1,
     * since a real rate is never legitimately 0 (every cost/price form
     * enforces `gt:0`) and dividing by 0 would throw.
     */
    public static function convertFromBase(string|int|float|null $baseAmount, string|int|float|null $rate, int $scale = 8): string
    {
        $rate = (string) ($rate ?? 1);

        if (bccomp($rate, '0', 6) === 0) {
            $rate = '1';
        }

        return bcdiv((string) ($baseAmount ?? 0), $rate, $scale);
    }

    /**
     * Rounds a non-negative amount half up to 2 decimal places. bcmath has no
     * native rounding, so this adds half a cent and truncates.
     */
    public static function roundToCents(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }

    /**
     * Like roundToCents(), but safe for a signed amount — a net profit/loss
     * figure that can legitimately be negative (unlike every other money
     * value in this app, which is always >= 0). Rounds the magnitude via
     * roundToCents() and reapplies the original sign, rather than adding
     * half a cent directly (which would round a negative amount the wrong
     * way, e.g. -50.556 -> -50.55 instead of -50.56).
     */
    public static function roundSignedToCents(string $amount): string
    {
        if (bccomp($amount, '0', 8) !== -1) {
            return static::roundToCents($amount);
        }

        $rounded = static::roundToCents(bcmul($amount, '-1', 8));

        return $rounded === '0.00' ? '0.00' : "-{$rounded}";
    }

    /**
     * The symbol used to identify a currency in a money display, falling
     * back to its code when Currency.symbol is unset (it's an optional
     * field). Empty when there's no currency at all — e.g. a field whose
     * sibling currency Select hasn't been chosen yet.
     */
    public static function currencySymbol(?Currency $currency): string
    {
        if ($currency === null) {
            return '';
        }

        return filled($currency->symbol) ? $currency->symbol : $currency->code;
    }

    /**
     * currencySymbol() with a trailing space, ready to prepend straight
     * onto a formatted amount string — for TextColumn/TextEntry ->prefix()
     * and raw text display, where prefix/suffix are plain string
     * concatenation (unlike TextInput's ->prefix(), which renders in its
     * own affix box and wants the bare symbol instead). Empty when there's
     * no currency, so no stray leading space appears.
     */
    public static function currencyPrefix(?Currency $currency): string
    {
        $symbol = static::currencySymbol($currency);

        return $symbol === '' ? '' : "{$symbol} ";
    }
}
