<?php

namespace App\Filament\Resources\Laptops\Tables;

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\LaptopStatus;
use App\Filament\Resources\RepairJobs\Schemas\RepairJobForm;
use App\Models\Agency;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\Sale;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class LaptopsTable
{
    public static function printBarcodeAction(): Action
    {
        return Action::make('printBarcode')
            ->label('Barcode')
            ->icon(Heroicon::OutlinedQrCode)
            ->url(fn (Laptop $record) => route('laptops.barcode', $record))
            ->openUrlInNewTab();
    }

    public static function printBarcodesBulkAction(): BulkAction
    {
        return BulkAction::make('printBarcodes')
            ->label('Print barcodes')
            ->icon(Heroicon::OutlinedQrCode)
            ->action(function (Collection $records, Component $livewire): void {
                $url = route('laptops.barcodes.print', ['ids' => $records->pluck('id')->implode(',')]);

                $livewire->js('window.open('.json_encode($url).", '_blank')");
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function sendForJobAction(): Action
    {
        return Action::make('sendForJob')
            ->label('Send for repair')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('warning')
            ->visible(fn (Laptop $record) => $record->activeRepairJob === null && $record->status !== LaptopStatus::Sold)
            ->modalHeading('Send for repair / repaint')
            ->modalSubmitActionLabel('Send')
            ->schema([
                ToggleButtons::make('type')
                    ->options(JobType::class)
                    ->grouped()
                    ->required(),
                ToggleButtons::make('assignee')
                    ->label('Assigned to')
                    ->options(JobAssignee::class)
                    ->grouped()
                    ->required()
                    ->live()
                    ->default(JobAssignee::InHouse),
                Select::make('agency_id')
                    ->label('Agency')
                    ->options(fn () => Agency::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(fn ($get) => JobAssignee::resolve($get('assignee')) === JobAssignee::Agency)
                    ->visible(fn ($get) => JobAssignee::resolve($get('assignee')) === JobAssignee::Agency),
                RepairJobForm::sentAtField(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ])
            ->action(function (Laptop $record, array $data): void {
                $record->repairJobs()->create($data);
                $record->refresh();
            });
    }

    public static function addToSaleAction(): Action
    {
        return Action::make('addToSale')
            ->label('Add to sale')
            ->icon(Heroicon::OutlinedShoppingBag)
            ->color('success')
            ->visible(fn (Laptop $record) => $record->status === LaptopStatus::InStock)
            ->modalHeading('Add to sale')
            ->schema([
                static::saleSelect(),
            ])
            ->action(function (Laptop $record, array $data): void {
                $sale = Sale::findOrFail($data['sale_id']);

                $sale->saleItems()->create([
                    'laptop_id' => $record->id,
                    'price_currency_id' => $sale->currency_id,
                    'price_exchange_rate' => $sale->exchange_rate,
                ]);

                $record->refresh();
            });
    }

    /**
     * Undoes addToSaleAction(): removes the laptop from whichever sale
     * reserved it, reverting its status to in stock (see SaleItem's
     * deleted() hook). Only offered while the laptop is Reserved (not yet
     * Sold) on a sale that's still a draft — once the sale is completed,
     * removing a laptop from it belongs in the Sale's own item list
     * (SaleItemsRelationManager), which enforces the same draft-only rule.
     */
    public static function removeFromSaleAction(): Action
    {
        return Action::make('removeFromSale')
            ->label('Remove from sale')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This removes the laptop from its sale and returns it to stock.')
            ->visible(fn (Laptop $record): bool => $record->status === LaptopStatus::Reserved
                && $record->saleItem !== null
                && ! $record->saleItem->sale->is_completed)
            ->action(function (Laptop $record): void {
                $record->saleItem?->delete();
                $record->refresh();
            });
    }

    public static function addToSaleBulkAction(): BulkAction
    {
        return BulkAction::make('addToSale')
            ->label('Add to sale')
            ->icon(Heroicon::OutlinedShoppingBag)
            ->color('success')
            ->modalHeading('Add to sale')
            ->schema([
                static::saleSelect(),
            ])
            ->action(function (Collection $records, array $data): void {
                $sale = Sale::findOrFail($data['sale_id']);

                $eligible = $records->where('status', LaptopStatus::InStock);

                $eligible->each(fn (Laptop $laptop) => $sale->saleItems()->firstOrCreate(
                    ['laptop_id' => $laptop->id],
                    [
                        'price_currency_id' => $sale->currency_id,
                        'price_exchange_rate' => $sale->exchange_rate,
                    ],
                ));

                if ($eligible->count() < $records->count()) {
                    Notification::make()
                        ->title('Some laptops were skipped')
                        ->body('Only in-stock laptops can be added to a sale.')
                        ->warning()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * A search-as-you-type select rather than ->options() (which would load
     * every sale up front — too slow once there are many) or
     * ->relationship('sale', ...) (this field doesn't correspond to a real
     * relationship on Laptop — a laptop's sale is reached indirectly via
     * SaleItem — and per the project's Filament rule, ->relationship()
     * doesn't reliably dehydrate inside a table row/bulk action's ->schema()
     * anyway). The query and label logic live on Sale (searchableOptions() /
     * optionLabel()) so they're reusable and testable outside this Select.
     */
    private static function saleSelect(): Select
    {
        return Select::make('sale_id')
            ->label('Sale')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Sale::searchableOptions($search))
            ->getOptionLabelUsing(fn ($value): ?string => Sale::with('buyer')->find($value)?->optionLabel())
            ->required();
    }

    public static function completeJobAction(): Action
    {
        return Action::make('completeJob')
            ->label('Mark job complete')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalDescription('This marks the active repair / repaint job as completed and returns the unit to stock.')
            ->visible(fn (Laptop $record) => $record->activeRepairJob !== null)
            ->schema([
                RepairJobForm::expenseField(boundToRecord: false, costRequired: true),
            ])
            ->fillForm(fn (Laptop $record): array => [
                'cost' => $record->activeRepairJob?->cost,
                'cost_currency_id' => $record->activeRepairJob?->cost_currency_id,
                'cost_exchange_rate' => $record->activeRepairJob?->cost_exchange_rate,
            ])
            ->action(function (Laptop $record, array $data): void {
                $record->activeRepairJob?->update([
                    'status' => JobStatus::Completed,
                    'cost' => $data['cost'],
                    'cost_currency_id' => $data['cost_currency_id'],
                    'cost_exchange_rate' => $data['cost_exchange_rate'],
                ]);
                $record->refresh();
            });
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('asset_code')
                    ->label('Serial Number')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('shipment.code')
                    ->label('Shipment')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('laptopModel.name')
                    ->label('Model')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('processor.name')
                    ->label('Processor')
                    ->searchable(),
                TextColumn::make('generation.name')
                    ->label('Generation')
                    ->toggleable(),
                TextColumn::make('ram_gb')
                    ->label('RAM')
                    ->suffix(' GB')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('storage_gb')
                    ->label('Storage')
                    ->suffix(' GB')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('serial_no')
                    ->label('SN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_battery_ok')->label('Battery')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_lcd_ok')->label('LCD')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_bezel_ok')->label('Bezel')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_top_cover_ok')->label('Top cover')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_body_ok')->label('Body')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_back_cover_ok')->label('Back cover')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_keyboard_ok')->label('Keyboard')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_touchpad_ok')->label('Touchpad')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('has_issues')
                    ->label('Issues')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('sale_summary')
                    ->label('Sold / Reserved to')
                    ->state(function (Laptop $record): ?string {
                        $saleItem = $record->saleItem;

                        if ($saleItem === null) {
                            return null;
                        }

                        $verb = $saleItem->sale->is_completed ? 'Sold to' : 'Reserved to';
                        $buyer = $saleItem->sale->buyer?->name ?? 'Walk-in buyer';

                        return "{$verb} {$buyer} ({$saleItem->sale->code})";
                    })
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('purchase_cost')
                    ->label('Purchase cost')
                    ->tooltip('This unit\'s share of its shipment\'s landed cost, in the base currency')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base()))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('repair_expense_total')
                    ->label('Repair expense')
                    ->tooltip('Every repair/repaint job on this unit, converted to the base currency')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base()))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_cost')
                    ->label('Total cost')
                    ->tooltip('Purchase cost plus repair expense, in the base currency')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base()))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('shipment_id')
                    ->relationship('shipment', 'code')
                    ->label('Shipment')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('brand_id')
                    ->relationship('brand', 'name')
                    ->label('Brand')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('laptop_model_id')
                    ->relationship('laptopModel', 'name')
                    ->label('Model')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('processor_id')
                    ->relationship('processor', 'name')
                    ->label('Processor')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('generation_id')
                    ->relationship('generation', 'name')
                    ->label('Generation')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('ram_gb')
                    ->label('RAM')
                    ->options(fn () => Laptop::query()
                        ->whereNotNull('ram_gb')
                        ->distinct()
                        ->orderBy('ram_gb')
                        ->pluck('ram_gb')
                        ->mapWithKeys(fn ($value) => [$value => "{$value} GB"]))
                    ->multiple(),
                SelectFilter::make('storage_gb')
                    ->label('Storage')
                    ->options(fn () => Laptop::query()
                        ->whereNotNull('storage_gb')
                        ->distinct()
                        ->orderBy('storage_gb')
                        ->pluck('storage_gb')
                        ->mapWithKeys(fn ($value) => [$value => "{$value} GB"]))
                    ->multiple(),
                TernaryFilter::make('has_builtin_ram')
                    ->label('Built-in memory'),
                SelectFilter::make('status')
                    ->options(LaptopStatus::class)
                    ->multiple(),
                TernaryFilter::make('has_issues'),
                TernaryFilter::make('is_battery_ok')->label('Battery'),
                TernaryFilter::make('is_lcd_ok')->label('LCD'),
                TernaryFilter::make('is_bezel_ok')->label('Bezel'),
                TernaryFilter::make('is_top_cover_ok')->label('Top cover'),
                TernaryFilter::make('is_body_ok')->label('Body'),
                TernaryFilter::make('is_back_cover_ok')->label('Back cover'),
                TernaryFilter::make('is_keyboard_ok')->label('Keyboard'),
                TernaryFilter::make('is_touchpad_ok')->label('Touchpad'),
                Filter::make('created_at')
                    ->label('Imported')
                    ->schema([
                        DatePicker::make('imported_from')->label('Imported from'),
                        DatePicker::make('imported_until')->label('Imported until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['imported_from'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['imported_until'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    })
                    ->columnSpan(2)
                    ->columns(2),
                TrashedFilter::make(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormSchema(fn (array $filters): array => [
                // Identification and Status & dates are the two shortest
                // groups, so they share a row instead of each claiming the
                // full width. Specification and Condition checklist stay
                // full-width but with wider internal grids (5 filters/row
                // instead of 3), cutting their own row count too.
                Section::make('Identification')
                    ->schema([
                        $filters['shipment_id'],
                        $filters['brand_id'],
                        $filters['laptop_model_id'],
                    ])
                    ->columns(3)
                    ->compact()
                    ->dense(),
                Section::make('Status & dates')
                    ->schema([
                        $filters['status'],
                        $filters['created_at'],
                        $filters['trashed'],
                    ])
                    ->columns(4)
                    ->compact()
                    ->dense(),
                Section::make('Specification')
                    ->schema([
                        $filters['processor_id'],
                        $filters['generation_id'],
                        $filters['ram_gb'],
                        $filters['storage_gb'],
                        $filters['has_builtin_ram'],
                    ])
                    ->columns(5)
                    ->columnSpanFull()
                    ->compact()
                    ->dense(),
                Section::make('Condition checklist')
                    ->schema([
                        $filters['has_issues'],
                        $filters['is_battery_ok'],
                        $filters['is_lcd_ok'],
                        $filters['is_bezel_ok'],
                        $filters['is_top_cover_ok'],
                        $filters['is_body_ok'],
                        $filters['is_back_cover_ok'],
                        $filters['is_keyboard_ok'],
                        $filters['is_touchpad_ok'],
                    ])
                    ->columns(5)
                    ->columnSpanFull()
                    ->compact()
                    ->dense(),
            ])
            ->filtersFormColumns(2)
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make(),
                static::printBarcodeAction(),
                static::addToSaleAction(),
                static::removeFromSaleAction(),
                static::sendForJobAction(),
                static::completeJobAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                static::printBarcodesBulkAction(),
                static::addToSaleBulkAction(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
