<?php

namespace App\Filament\Pages\Reports\Concerns;

use App\Support\Reports\ReportDateRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Gives a report page the same date-range filter (a preset Select, plus a
 * custom from/to pair shown only for "Custom range") used by every page
 * under App\Filament\Pages\Reports, rendered above the page's own content
 * via content().
 *
 * content() only needs to add the filters form — footer widgets are NOT
 * added here. The base Page's Blade template (components/page/index.blade.php)
 * already echoes `{{ $this->footerWidgets }}` unconditionally for every
 * page, resolved from getFooterWidgets() independently of content(); adding
 * a widgets Grid here too would render every widget twice.
 *
 * Widgets registered via getFooterWidgets() automatically receive the
 * resolved filter state as $this->pageFilters (raw array) if they use
 * Filament\Widgets\Concerns\InteractsWithPageFilters — Filament wires this
 * up for any page exposing a `filters` property (which HasFiltersForm
 * provides), not just the Dashboard. Widgets should call
 * ReportDateRange::fromFilters($this->pageFilters ?? []) themselves rather
 * than duplicating the preset-resolution logic.
 */
trait HasReportDateRangeFilter
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('preset')
                    ->label('Date range')
                    ->options(ReportDateRange::presetOptions())
                    ->default('this_month')
                    ->native(false)
                    ->live(),
                DatePicker::make('from')
                    ->visible(fn (Get $get): bool => $get('preset') === 'custom'),
                DatePicker::make('to')
                    ->visible(fn (Get $get): bool => $get('preset') === 'custom'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedSchema::make('filtersForm'),
            ]);
    }

    public function getReportDateRange(): ReportDateRange
    {
        return ReportDateRange::fromFilters($this->filters ?? []);
    }
}
