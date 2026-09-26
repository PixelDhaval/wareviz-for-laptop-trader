<?php

namespace App\Filament\Resources\Shipments\Schemas;

use App\Enums\ShipmentCostType;
use App\Enums\ShipmentType;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\Shipment;
use App\Support\ExchangeRateFetcher;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ShipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->dense()
            ->components([
                TextInput::make('code')
                    ->label('Shipment / container code')
                    ->required()
                    ->unique(ignoreRecord: true),
                ToggleButtons::make('type')
                    ->options(ShipmentType::class)
                    ->grouped()
                    ->required()
                    ->live()
                    ->default(ShipmentType::Import)
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::refreshAllCostCurrencies($get, $set)),
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm(fn (Schema $schema) => SupplierForm::configure($schema)),
                TextInput::make('name'),
                DatePicker::make('received_at')
                    ->default(now()),
                DatePicker::make('invoice_date')
                    ->helperText('Used to look up each cost line\'s historical exchange rate.')
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::refreshAllCostExchangeRates($get, $set))
                    ->suffixAction(
                        Action::make('fetchExchangeRates')
                            ->label('Fetch exchange rate')
                            ->icon(Heroicon::OutlinedArrowPath)
                            // An action embedded in a field (via suffixAction) runs in its
                            // own closure context — Get/Set must be injected as $schemaGet
                            // /$schemaSet to reach the surrounding form's state, not $get/$set.
                            ->action(fn (Get $schemaGet, Set $schemaSet) => static::refreshAllCostExchangeRates($schemaGet, $schemaSet)),
                    ),
                Toggle::make('is_completed')
                    ->helperText('Mark as completed once all units from the packing list have been imported.'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Section::make('Costs & expenses')
                    ->compact()
                    ->description(function (): string {
                        $baseCode = Currency::base()?->code;

                        return $baseCode
                            ? "Enter each amount in its own currency with the exchange rate to {$baseCode}. The rate is saved with this shipment, so later rate changes never alter its cost."
                            : 'No base currency is set yet. Mark one under Catalog → Currencies so every cost can be converted into it.';
                    })
                    ->columnSpanFull()
                    ->schema([
                        ...array_map(static::costLine(...), ShipmentCostType::cases()),
                        static::costSummary(),
                    ]),
            ]);
    }

    /**
     * One row per cost: amount, currency and the exchange rate to the base
     * currency. Currency and rate are only mandatory once an amount is entered.
     */
    private static function costLine(ShipmentCostType $type): FusedGroup
    {
        $amountField = $type->value;
        $rateField = $type->exchangeRateColumn();

        $hasAmount = fn (Get $get): bool => (float) $get($amountField) > 0;
        $defaultCurrencyId = fn (Get $get): ?int => static::defaultCostCurrencyId($type, $get);

        return FusedGroup::make([
            TextInput::make($amountField)
                ->placeholder('Amount')
                ->numeric()
                ->minValue(0)
                ->maxValue(999999999999.99)
                ->default(0)
                ->required()
                ->live(onBlur: true)
                ->prefix(fn (Get $get): string => Money::currencySymbol(Currency::find($get($type->currencyColumn()))))
                ->columnSpan(2),
            Select::make($type->currencyColumn())
                ->placeholder('Currency')
                ->relationship($type->currencyRelationship(), 'code')
                ->searchable()
                ->preload()
                ->required($hasAmount)
                ->live()
                ->default($defaultCurrencyId)
                ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => static::updateCostExchangeRate($type, $get, $set, $state)),
            TextInput::make($rateField)
                ->placeholder('Rate to base')
                ->numeric()
                ->rule('gt:0')
                ->required($hasAmount)
                ->live(onBlur: true)
                ->helperText('Auto-filled from the historical rate on the invoice date when available.')
                ->default(fn (Get $get): ?string => Currency::find($defaultCurrencyId($get))?->exchange_rate)
                ->dehydrateStateUsing(fn (mixed $state): mixed => filled($state) ? $state : 1),
        ])
            ->label($type->getLabel())
            ->columns(4);
    }

    /**
     * The default currency id to preselect on a cost line, per the
     * shipment's own type (a local purchase uses one single currency for
     * every line; an import purchase uses each line's own setting).
     */
    private static function defaultCostCurrencyId(ShipmentCostType $type, Get $get): ?int
    {
        return Setting::current()->shipmentCostCurrencyId($type, ShipmentType::resolve($get('type')));
    }

    /**
     * Re-applies each cost line's currency default (and exchange rate) for
     * the shipment's now-current type. Used when `type` changes, since a
     * local purchase's currency default differs from an import's.
     */
    private static function refreshAllCostCurrencies(Get $get, Set $set): void
    {
        foreach (ShipmentCostType::cases() as $type) {
            $currencyId = static::defaultCostCurrencyId($type, $get);

            $set($type->currencyColumn(), $currencyId);
            static::updateCostExchangeRate($type, $get, $set, $currencyId);
        }
    }

    /**
     * Prefills a cost line's exchange rate from ExchangeRateFetcher's
     * historical rate for the shipment's invoice date, falling back to the
     * currency's current Currency::exchange_rate when the date is missing
     * or the API can't supply a rate for it. Shared by each cost line's
     * currency Select and the invoice_date field's afterStateUpdated (which
     * re-applies it to every already-selected cost line's currency).
     */
    private static function updateCostExchangeRate(ShipmentCostType $type, Get $get, Set $set, mixed $currencyId): void
    {
        $rateField = $type->exchangeRateColumn();

        if (blank($currencyId)) {
            $set($rateField, null);

            return;
        }

        $currency = Currency::find($currencyId);

        if (! $currency) {
            return;
        }

        $invoiceDate = $get('invoice_date');

        $rate = filled($invoiceDate)
            ? ExchangeRateFetcher::rate($currency, Carbon::parse($invoiceDate))
            : null;

        $set($rateField, $rate ?? $currency->exchange_rate);
    }

    /**
     * Re-applies updateCostExchangeRate() to every cost line that already
     * has a currency selected. Used both when invoice_date changes and by
     * the date field's manual "Fetch exchange rate" suffix action.
     */
    private static function refreshAllCostExchangeRates(Get $get, Set $set): void
    {
        foreach (ShipmentCostType::cases() as $type) {
            static::updateCostExchangeRate($type, $get, $set, $get($type->currencyColumn()));
        }
    }

    private static function costSummary(): Grid
    {
        return Grid::make(3)
            ->schema([
                TextEntry::make('summary_total_cost')
                    ->label('Total cost')
                    ->state(fn (Get $get, ?Shipment $record): string => Money::currencyPrefix(Currency::base()).number_format(
                        (float) static::previewShipment($get, $record)->total_cost,
                        2,
                    )),
                TextEntry::make('summary_laptops')
                    ->label('Laptops')
                    ->state(fn (?Shipment $record): int => $record?->laptops()->count() ?? 0),
                TextEntry::make('summary_average_cost')
                    ->label('Average cost per laptop')
                    ->state(function (Get $get, ?Shipment $record): string {
                        $average = static::previewShipment($get, $record)->average_cost_per_laptop;

                        return $average === null ? '—' : Money::currencyPrefix(Currency::base()).number_format((float) $average, 2);
                    }),
            ]);
    }

    /**
     * An unsaved shipment carrying the amounts currently typed into the form,
     * so the summary uses the same calculation as the saved record.
     */
    private static function previewShipment(Get $get, ?Shipment $record): Shipment
    {
        $shipment = new Shipment;

        foreach (ShipmentCostType::cases() as $type) {
            $amount = $get($type->value);
            $rate = $get($type->exchangeRateColumn());

            $shipment->{$type->value} = is_numeric($amount) ? $amount : 0;
            $shipment->{$type->exchangeRateColumn()} = is_numeric($rate) ? $rate : 1;
        }

        $shipment->laptops_count = $record?->laptops()->count() ?? 0;

        return $shipment;
    }
}
