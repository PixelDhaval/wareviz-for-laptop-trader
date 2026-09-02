<?php

namespace App\Filament\Imports;

use App\Models\Brand;
use App\Models\Generation;
use App\Models\Laptop;
use App\Models\LaptopModel;
use App\Models\Processor;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class LaptopImporter extends Importer
{
    protected static ?string $model = Laptop::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('serial_no')
                ->label('Serial no (packing list SN)')
                ->guess(['sn', 'serial', 'serial no']),

            ImportColumn::make('brand')
                ->guess(['brand'])
                ->rules(['required'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('model')
                ->guess(['model'])
                ->rules(['required'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('processor')
                ->guess(['core', 'cpu', 'processor'])
                ->rules(['required'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('generation')
                ->guess(['gen', 'generation'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('ram_gb')
                ->label('RAM (GB)')
                ->guess(['ram'])
                ->integer(),

            ImportColumn::make('storage_gb')
                ->label('Storage (GB)')
                ->guess(['hard', 'storage', 'hdd', 'ssd'])
                ->integer(),

            ImportColumn::make('is_battery_ok')
                ->label('Battery')
                ->guess(['battery'])
                ->castStateUsing(fn (?string $state) => static::isOk($state)),

            ImportColumn::make('is_lcd_ok')
                ->label('LCD')
                ->guess(['lcd'])
                ->castStateUsing(fn (?string $state) => static::isOk($state)),

            ImportColumn::make('is_bezel_ok')
                ->label('Bezel')
                ->guess(['bazzel', 'bezel'])
                ->castStateUsing(fn (?string $state) => static::isOk($state)),

            ImportColumn::make('is_top_cover_ok')
                ->label('Top cover')
                ->guess(['top', 'top cover'])
                ->castStateUsing(fn (?string $state) => static::isOk($state)),

            ImportColumn::make('is_body_ok')
                ->label('Body')
                ->guess(['body'])
                ->castStateUsing(fn (?string $state) => static::isOk($state)),

            ImportColumn::make('is_back_cover_ok')
                ->label('Back cover')
                ->guess(['back cover', 'backcover'])
                ->castStateUsing(fn (?string $state) => static::isOk($state)),

            ImportColumn::make('is_keyboard_ok')
                ->label('Keyboard')
                ->guess(['keyboard'])
                ->castStateUsing(fn (?string $state) => static::isOk($state)),

            ImportColumn::make('is_touchpad_ok')
                ->label('Touchpad / click')
                ->guess(['click', 'touchpad'])
                ->castStateUsing(fn (?string $state) => static::isOk($state)),

            ImportColumn::make('builtin_ram_and_hard')
                ->label('Built-in RAM & storage')
                ->guess(['built in ram & hard', 'built in ram and hard', 'builtin'])
                ->fillRecordUsing(function (Laptop $record, ?string $state): void {
                    [$hasBuiltin, $ram, $storage] = static::parseBuiltIn($state);
                    $record->has_builtin_ram = $hasBuiltin;
                    $record->builtin_ram_gb = $ram;
                    $record->builtin_storage_gb = $storage;
                }),

            ImportColumn::make('issues')
                ->guess(['issues', 'remarks']),
        ];
    }

    public function resolveRecord(): Laptop
    {
        // Packing list serial numbers are a running tally, not a true unique
        // serial, so they can repeat within a shipment for distinct units.
        // Every row is a separate physical laptop and always creates a new record.
        return new Laptop(['shipment_id' => $this->options['shipment_id'] ?? null]);
    }

    protected function beforeFill(): void
    {
        $brand = Brand::firstOrCreate([
            'name' => Str::of((string) ($this->data['brand'] ?? ''))->squish()->title()->toString(),
        ]);
        $this->record->brand_id = $brand->id;

        $modelName = Str::of((string) ($this->data['model'] ?? ''))->squish()->upper()->toString();
        if ($modelName !== '') {
            $model = LaptopModel::firstOrCreate([
                'brand_id' => $brand->id,
                'name' => $modelName,
            ]);
            $this->record->laptop_model_id = $model->id;
        }

        $processorName = Str::of((string) ($this->data['processor'] ?? ''))->squish()->toString();
        if ($processorName !== '') {
            $processor = Processor::firstOrCreate(['name' => $processorName]);
            $this->record->processor_id = $processor->id;
        }

        $generationName = Str::of((string) ($this->data['generation'] ?? ''))->squish()->toString();
        if ($generationName !== '') {
            $generation = Generation::firstOrCreate(['name' => $generationName]);
            $this->record->generation_id = $generation->id;
        }
    }

    protected static function isOk(?string $state): bool
    {
        return strtoupper(trim((string) $state)) === 'OK';
    }

    /**
     * @return array{0: bool, 1: ?int, 2: ?int}
     */
    protected static function parseBuiltIn(?string $state): array
    {
        $state = strtoupper(trim((string) $state));

        if ($state === '' || $state === 'NO') {
            return [false, null, null];
        }

        if (str_contains($state, '/')) {
            [$ram, $storage] = array_pad(explode('/', $state, 2), 2, null);

            return [true, static::extractNumber($ram), static::extractNumber($storage)];
        }

        return [true, static::extractNumber($state), null];
    }

    protected static function extractNumber(?string $value): ?int
    {
        if ($value === null || preg_match('/\d+/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your laptop import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
