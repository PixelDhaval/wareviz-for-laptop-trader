<?php

namespace App\Filament\Resources\Laptops\Tables;

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\LaptopStatus;
use App\Models\Agency;
use App\Models\Laptop;
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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
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
            ->visible(fn (Laptop $record) => $record->activeRepairJob === null)
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
                TextInput::make('cost')
                    ->label('Expense')
                    ->numeric()
                    ->minValue(0),
                DatePicker::make('sent_at')
                    ->label('Sent on')
                    ->default(now()),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ])
            ->action(function (Laptop $record, array $data): void {
                $record->repairJobs()->create($data);
                $record->refresh();
            });
    }

    public static function completeJobAction(): Action
    {
        return Action::make('completeJob')
            ->label('Mark job complete')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('This marks the active repair / repaint job as completed and returns the unit to stock.')
            ->visible(fn (Laptop $record) => $record->activeRepairJob !== null)
            ->action(function (Laptop $record): void {
                $record->activeRepairJob?->update(['status' => JobStatus::Completed]);
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
                Section::make('Identification')
                    ->schema([
                        $filters['shipment_id'],
                        $filters['brand_id'],
                        $filters['laptop_model_id'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Specification')
                    ->schema([
                        $filters['processor_id'],
                        $filters['generation_id'],
                        $filters['ram_gb'],
                        $filters['storage_gb'],
                        $filters['has_builtin_ram'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
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
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Status & dates')
                    ->schema([
                        $filters['status'],
                        $filters['created_at'],
                        $filters['trashed'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make(),
                static::printBarcodeAction(),
                static::sendForJobAction(),
                static::completeJobAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                static::printBarcodesBulkAction(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
