<?php

namespace App\Filament\Resources\Laptops\Schemas;

use App\Enums\LaptopStatus;
use App\Filament\Resources\Brands\Schemas\BrandForm;
use App\Filament\Resources\Processors\Schemas\ProcessorForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LaptopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                static::identificationSection(),

                Section::make('Specification')
                    ->columns(3)
                    ->schema(static::specificationFields()),

                Section::make('Condition checklist')
                    ->description('Uncheck any part that failed inspection. Notes go in Issues below.')
                    ->schema(static::conditionFields()),

                ToggleButtons::make('status')
                    ->options(LaptopStatus::class)
                    ->default(LaptopStatus::InStock)
                    ->grouped()
                    ->required(),
            ]);
    }

    public static function identificationSection(): Section
    {
        return Section::make('Identification')
            ->columns(2)
            ->schema([
                Select::make('shipment_id')
                    ->relationship('shipment', 'code')
                    ->searchable()
                    ->preload()
                    ->required(),
                ...static::identificationFields(),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    public static function identificationFields(): array
    {
        return [
            TextInput::make('serial_no')
                ->label('Packing list SN')
                ->helperText('Serial number as printed on the shipment packing list (can repeat across units).'),
            TextInput::make('asset_code')
                ->label('Serial Number')
                ->disabled()
                ->dehydrated(false)
                ->placeholder('Will be generated automatically after saving')
                ->helperText('System-generated, incremental, used for the barcode.')
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function specificationFields(bool $includeBrand = true, bool $includeProcessor = true, ?int $fixedBrandId = null): array
    {
        $fields = [];

        if ($includeBrand) {
            $fields[] = Select::make('brand_id')
                ->relationship('brand', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->createOptionForm(fn (Schema $schema) => BrandForm::configure($schema));
        }

        $fields[] = Select::make('laptop_model_id')
            ->label('Model')
            ->relationship(
                name: 'laptopModel',
                titleAttribute: 'name',
                modifyQueryUsing: fn ($query, $get) => $query->where('brand_id', $includeBrand ? $get('brand_id') : $fixedBrandId),
            )
            ->searchable()
            ->preload()
            ->required()
            ->createOptionForm(fn (Schema $schema) => $schema->components([
                TextInput::make('name')->required(),
            ]))
            ->createOptionAction(fn ($action) => $action->modalHeading('Add model'))
            ->helperText($includeBrand ? 'Choose a brand first.' : null);

        if ($includeProcessor) {
            $fields[] = Select::make('processor_id')
                ->label('Processor')
                ->relationship('processor', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->createOptionForm(fn (Schema $schema) => ProcessorForm::configure($schema));
        }

        $fields[] = Select::make('generation_id')
            ->label('Generation')
            ->relationship('generation', 'name')
            ->searchable()
            ->preload()
            ->createOptionForm(fn (Schema $schema) => $schema->components([
                TextInput::make('name')->required(),
            ]))
            ->createOptionAction(fn ($action) => $action->modalHeading('Add generation'))
            ->helperText('e.g. 11th Gen, Ryzen 5 Pro');

        $fields[] = TextInput::make('ram_gb')
            ->label('RAM (GB)')
            ->numeric()
            ->suffix('GB');
        $fields[] = TextInput::make('storage_gb')
            ->label('Storage (GB)')
            ->numeric()
            ->suffix('GB');
        $fields[] = Toggle::make('has_builtin_ram')
            ->label('Has built-in / soldered memory')
            ->live();
        $fields[] = TextInput::make('builtin_ram_gb')
            ->label('Built-in RAM (GB)')
            ->numeric()
            ->suffix('GB')
            ->visible(fn ($get) => $get('has_builtin_ram'));
        $fields[] = TextInput::make('builtin_storage_gb')
            ->label('Built-in storage (GB)')
            ->numeric()
            ->suffix('GB')
            ->visible(fn ($get) => $get('has_builtin_ram'));

        return $fields;
    }

    /**
     * @return array<int, Component>
     */
    public static function conditionFields(): array
    {
        return [
            Grid::make(4)
                ->schema([
                    Toggle::make('is_battery_ok')->label('Battery')->default(true),
                    Toggle::make('is_lcd_ok')->label('LCD')->default(true),
                    Toggle::make('is_bezel_ok')->label('Bezel')->default(true),
                    Toggle::make('is_top_cover_ok')->label('Top cover')->default(true),
                    Toggle::make('is_body_ok')->label('Body')->default(true),
                    Toggle::make('is_back_cover_ok')->label('Back cover')->default(true),
                    Toggle::make('is_keyboard_ok')->label('Keyboard')->default(true),
                    Toggle::make('is_touchpad_ok')->label('Touchpad / click')->default(true),
                ]),
            Textarea::make('issues')
                ->label('Issues / remarks')
                ->columnSpanFull(),
        ];
    }
}
