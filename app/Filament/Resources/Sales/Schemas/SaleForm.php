<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\SaleType;
use App\Filament\Resources\Buyers\Schemas\BuyerForm;
use App\Models\Currency;
use App\Models\Sale;
use App\Models\Setting;
use App\Support\ExchangeRateFetcher;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->dense()
            ->components([
                Section::make('Sale')
                    ->columns(2)
                    ->columnSpanFull()
                    ->compact()
                    ->schema([
                        TextInput::make('code')
                            ->label('Sale / invoice code')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Generated automatically from Settings → Sale code.')
                            ->visible(fn (?Sale $record): bool => $record !== null),
                        ToggleButtons::make('type')
                            ->options(SaleType::class)
                            ->grouped()
                            ->required()
                            ->live()
                            ->default(SaleType::Local)
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                $currencyId = ($type = SaleType::resolve($state))
                                    ? Setting::current()->saleCurrencyId($type)
                                    : null;

                                if ($currencyId === null) {
                                    return;
                                }

                                $set('currency_id', $currencyId);
                                static::updateExchangeRate($get, $set, $currencyId);
                            }),
                        Select::make('buyer_id')
                            ->label(fn (Get $get) => SaleType::resolve($get('type')) === SaleType::Export ? 'Consignee' : 'Buyer')
                            ->relationship('buyer', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm(fn (Schema $schema) => BuyerForm::configure($schema)),
                        TextInput::make('destination_country')
                            ->required(fn (Get $get) => SaleType::resolve($get('type')) === SaleType::Export)
                            ->visible(fn (Get $get) => SaleType::resolve($get('type')) === SaleType::Export),
                        TextInput::make('reference_no')
                            ->label('Invoice / reference no.')
                            ->required(fn (Get $get) => SaleType::resolve($get('type')) === SaleType::Export)
                            ->visible(fn (Get $get) => SaleType::resolve($get('type')) === SaleType::Export),
                        DatePicker::make('sold_at')
                            ->label('Sale date')
                            ->default(now())
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateExchangeRate($get, $set, $get('currency_id')))
                            ->suffixAction(
                                Action::make('fetchExchangeRate')
                                    ->label('Fetch exchange rate')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    // An action embedded in a field runs in its own closure
                                    // context — Get/Set must be injected as $schemaGet/
                                    // $schemaSet to reach the surrounding form's state.
                                    ->action(fn (Get $schemaGet, Set $schemaSet) => static::updateExchangeRate($schemaGet, $schemaSet, $schemaGet('currency_id'))),
                            ),
                        Toggle::make('is_completed')
                            ->helperText('Mark as completed once every laptop has been scanned in and priced.'),
                    ]),

                Section::make('Currency')
                    ->description('The default currency and exchange rate for laptops scanned or added to this sale. Each laptop keeps its own snapshot, so a later change here never alters an already-scanned item.')
                    ->columnSpanFull()
                    ->compact()
                    ->schema([
                        static::currencyField(),
                    ]),

                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function currencyField(): FusedGroup
    {
        return FusedGroup::make([
            Select::make('currency_id')
                ->label('Currency')
                ->relationship('currency', 'code')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->default(fn (Get $get): ?int => static::defaultCurrencyId($get))
                ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => static::updateExchangeRate($get, $set, $state)),
            TextInput::make('exchange_rate')
                ->label('Rate to base')
                ->numeric()
                ->rule('gt:0')
                ->required()
                ->helperText('Auto-filled from the historical rate on the sale date when available.')
                ->default(fn (Get $get): ?string => Currency::find(static::defaultCurrencyId($get))?->exchange_rate),
        ])
            ->columns(2)
            ->columnSpanFull();
    }

    private static function defaultCurrencyId(Get $get): ?int
    {
        $type = SaleType::resolve($get('type'));

        return $type ? Setting::current()->saleCurrencyId($type) : null;
    }

    /**
     * Prefills exchange_rate from ExchangeRateFetcher's historical rate for
     * the sale's own date, falling back to the currency's current
     * Currency::exchange_rate when the date is missing or the API can't
     * supply a rate for it. Shared by the type, currency_id and sold_at
     * fields' afterStateUpdated callbacks, since a change to any of the
     * three can affect which rate applies.
     */
    private static function updateExchangeRate(Get $get, Set $set, mixed $currencyId): void
    {
        if (blank($currencyId)) {
            $set('exchange_rate', null);

            return;
        }

        $currency = Currency::find($currencyId);

        if (! $currency) {
            return;
        }

        $soldAt = $get('sold_at');

        $rate = filled($soldAt)
            ? ExchangeRateFetcher::rate($currency, Carbon::parse($soldAt))
            : null;

        $set('exchange_rate', $rate ?? $currency->exchange_rate);
    }
}
