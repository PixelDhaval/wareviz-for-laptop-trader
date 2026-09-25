<?php

namespace App\Support;

use App\Enums\CodeDateSource;
use App\Enums\CodeSegmentPosition;
use App\Models\CodeSequence;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * Builds a Laptop asset code or Sale code from Setting::current()'s
 * configured prefix/suffix/date format/sequence pad. Each code type keeps
 * its own independent, period-scoped sequence counter in code_sequences —
 * see App\Models\CodeSequence and .ai/rules/pages.md.
 */
class CodeGenerator
{
    public static function forLaptop(Laptop $laptop): string
    {
        $settings = Setting::current();

        $dateSegment = static::laptopDateSegment($laptop, $settings);

        return static::assemble(
            prefix: $settings->laptop_code_prefix,
            suffix: $settings->laptop_code_suffix,
            separator: $settings->laptop_code_separator ?? '',
            dateSegment: $dateSegment,
            dateBeforeSequence: true,
            sequenceNumber: CodeSequence::next('laptop', $dateSegment ?? ''),
            sequencePad: $settings->laptop_code_sequence_pad ?? 6,
        );
    }

    public static function forSale(Sale $sale): string
    {
        $settings = Setting::current();

        $dateSegment = $settings->sale_code_date_format?->format(
            $sale->sold_at ?? now(),
            $settings->fiscal_year_start_month,
        );

        return static::assemble(
            prefix: $settings->sale_code_prefix,
            suffix: $settings->sale_code_suffix,
            separator: $settings->sale_code_separator ?? '',
            dateSegment: $dateSegment,
            dateBeforeSequence: ($settings->sale_code_date_position ?? CodeSegmentPosition::Before) === CodeSegmentPosition::Before,
            sequenceNumber: CodeSequence::next('sale', $dateSegment ?? ''),
            sequencePad: $settings->sale_code_sequence_pad ?? 4,
        );
    }

    private static function laptopDateSegment(Laptop $laptop, Setting $settings): ?string
    {
        if ($settings->laptop_code_date_format === null) {
            return null;
        }

        $source = $settings->laptop_code_date_source ?? CodeDateSource::CreatedAt;

        /** @var CarbonInterface $date */
        $date = match ($source) {
            CodeDateSource::ShipmentReceivedAt => $laptop->shipment?->received_at ?? $laptop->created_at,
            CodeDateSource::CreatedAt => $laptop->created_at,
        } ?? now();

        return $settings->laptop_code_date_format->format($date, $settings->fiscal_year_start_month);
    }

    private static function assemble(
        ?string $prefix,
        ?string $suffix,
        string $separator,
        ?string $dateSegment,
        bool $dateBeforeSequence,
        int $sequenceNumber,
        int $sequencePad,
    ): string {
        $sequencePart = str_pad((string) $sequenceNumber, $sequencePad, '0', STR_PAD_LEFT);

        $middle = match (true) {
            $dateSegment === null => [$sequencePart],
            $dateBeforeSequence => [$dateSegment, $sequencePart],
            default => [$sequencePart, $dateSegment],
        };

        $segments = array_filter([$prefix, ...$middle, $suffix], fn (?string $segment): bool => filled($segment));

        return implode($separator, $segments);
    }
}
