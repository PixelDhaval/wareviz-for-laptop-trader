<?php

namespace App\Support;

use App\Models\Currency;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches a historical exchange rate for a given currency on a given date
 * from the free, no-key-required exchange-api
 * (https://github.com/fawazahmed0/exchange-api), so a Sale or Shipment cost
 * line's rate can be prefilled from the rate that actually applied on its
 * invoice date, rather than whatever Currency.exchange_rate holds today.
 * Callers still snapshot whatever ends up in the form's exchange_rate field
 * at save time — see the note in .ai/rules/models.md about rates never
 * being recomputed after the fact.
 */
class ExchangeRateFetcher
{
    private const JSDELIVR_URL = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@%s/v1/currencies/%s.json';

    private const FALLBACK_URL = 'https://%s.currency-api.pages.dev/v1/currencies/%s.json';

    /**
     * The rate of 1 unit of $currency in the base currency, on $date, as a
     * 6dp decimal string. Null when there's no base currency configured, the
     * date is in the future, the currency isn't recognised by the API, or
     * the API can't be reached (jsdelivr, then a Cloudflare fallback, per
     * the project's warning about needing a fallback).
     *
     * Results are cached forever per (date, currency) pair — a historical
     * rate, once published, never changes.
     */
    public static function rate(Currency $currency, CarbonInterface $date): ?string
    {
        $base = Currency::base();

        if ($base === null) {
            return null;
        }

        if ($currency->is($base)) {
            return '1.000000';
        }

        if ($date->isFuture()) {
            return null;
        }

        return Cache::rememberForever(
            static::cacheKey($currency, $base, $date),
            fn (): ?string => static::fetch($currency, $base, $date),
        );
    }

    private static function fetch(Currency $currency, Currency $base, CarbonInterface $date): ?string
    {
        $dateSegment = $date->format('Y-m-d');
        $code = strtolower($currency->code);
        $baseCode = strtolower($base->code);

        return static::fetchFrom(sprintf(self::JSDELIVR_URL, $dateSegment, $code), $code, $baseCode)
            ?? static::fetchFrom(sprintf(self::FALLBACK_URL, $dateSegment, $code), $code, $baseCode);
    }

    private static function fetchFrom(string $url, string $code, string $baseCode): ?string
    {
        try {
            $response = Http::timeout(5)->get($url);
        } catch (Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        // The response nests every rate under the requested currency's own
        // code, e.g. {"date": "...", "usd": {"inr": 83.5, ...}}.
        $value = $response->json("{$code}.{$baseCode}");

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 6, '.', '');
    }

    private static function cacheKey(Currency $currency, Currency $base, CarbonInterface $date): string
    {
        return sprintf(
            'exchange-rate:%s:%s:%s',
            $date->format('Y-m-d'),
            strtolower($currency->code),
            strtolower($base->code),
        );
    }
}
