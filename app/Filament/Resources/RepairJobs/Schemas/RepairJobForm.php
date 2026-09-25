<?php

namespace App\Filament\Resources\RepairJobs\Schemas;

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Currency;
use App\Models\RepairJob;
use App\Models\Setting;
use App\Support\ExchangeRateFetcher;
use App\Support\Money;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class RepairJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->dense()
            ->components([
                Select::make('laptop_id')
                    ->label('Laptop')
                    ->relationship('laptop', 'asset_code')
                    ->searchable()
                    ->preload()
                    ->required(),
                Grid::make(2)
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
                    ]),
                Select::make('agency_id')
                    ->label('Agency')
                    ->relationship('agency', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn ($get) => JobAssignee::resolve($get('assignee')) === JobAssignee::Agency)
                    ->visible(fn ($get) => JobAssignee::resolve($get('assignee')) === JobAssignee::Agency),
                static::statusField(),
                static::sentAtField(),
                static::expenseField(costRequired: static::isBeingCompleted(...))
                    ->visible(fn (?RepairJob $record, Get $get): bool => $record !== null && static::isBeingCompleted($get)),
                DatePicker::make('completed_at')
                    ->label('Completed on')
                    ->visible(fn ($get) => JobStatus::resolve($get('status')) === JobStatus::Completed),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * True once the job's status has been set to Completed — the point at
     * which the expense is actually known and asked for. Shared by
     * expenseField()'s $costRequired and configure()'s ->visible() so both
     * stay in sync.
     */
    public static function isBeingCompleted(Get $get): bool
    {
        return JobStatus::resolve($get('status')) === JobStatus::Completed;
    }

    /**
     * The expense row shared by every place a repair/repaint job is created
     * or edited: an amount, its currency and the exchange rate to the base
     * currency, saved with the job so a later change to a currency's rate
     * never alters a past job's cost.
     *
     * The expense isn't known at intake — it's asked for when the job is
     * marked completed (see configure(), which only shows this field once
     * editing a record whose status is Completed) or when completeJobAction()
     * is confirmed. `$costRequired` makes the amount itself mandatory in
     * that context; elsewhere it stays optional and currency/rate are only
     * required once an amount is actually entered.
     *
     * `$boundToRecord` selects how the currency is loaded: `true` (the
     * default) uses `->relationship()`, which needs a bound Eloquent record
     * (a resource form or a relation manager). Pass `false` inside a bare
     * `Action::make()->schema()` with no bound record — such as ScanLookup's
     * and LaptopsTable's "Send for repair"/"Mark job complete" modals —
     * where `->relationship()` does not reliably dehydrate; see
     * .ai/rules/filament.md.
     */
    public static function expenseField(bool $boundToRecord = true, bool|Closure $costRequired = false): FusedGroup
    {
        $isCostRequired = fn (Get $get): bool => is_callable($costRequired) ? (bool) $costRequired($get) : $costRequired;
        $hasAmount = fn (Get $get): bool => ((float) $get('cost') > 0) || $isCostRequired($get);
        $defaultCurrencyId = Setting::current()->repair_cost_currency_id;

        $currencySelect = Select::make('cost_currency_id')
            ->label('Currency')
            ->placeholder('Currency')
            ->searchable()
            ->live()
            ->required($hasAmount)
            ->default($defaultCurrencyId)
            ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => static::updateExpenseExchangeRate($get, $set, $state));

        $currencySelect = $boundToRecord
            ? $currencySelect->relationship('currency', 'code')->preload()
            : $currencySelect->options(fn () => Currency::query()->orderBy('code')->pluck('code', 'id'));

        return FusedGroup::make([
            TextInput::make('cost')
                ->label('Expense')
                ->placeholder('Amount')
                ->numeric()
                ->minValue(0)
                ->required($isCostRequired)
                ->live(onBlur: true)
                ->prefix(fn (Get $get): string => Money::currencySymbol(Currency::find($get('cost_currency_id'))))
                ->columnSpan(2),
            $currencySelect,
            TextInput::make('cost_exchange_rate')
                ->label('Rate to base')
                ->placeholder('Rate to base')
                ->numeric()
                ->rule('gt:0')
                ->required($hasAmount)
                ->live(onBlur: true)
                ->helperText('Auto-filled from the historical rate on the date sent when available.')
                ->default(fn (): ?string => Currency::find($defaultCurrencyId)?->exchange_rate)
                ->afterStateHydrated(function (Get $get, Set $set): void {
                    if (blank($get('cost_currency_id'))) {
                        $set('cost_exchange_rate', null);
                    }
                })
                ->dehydrateStateUsing(fn (mixed $state, Get $get): mixed => filled($state) ? $state : ($hasAmount($get) ? null : 1)),
        ])
            ->label('Expense')
            ->columns(4)
            ->columnSpanFull();
    }

    /**
     * The status ToggleButtons, shared by every place a repair/repaint job
     * is created or edited. Filament's ->default() on a field only applies
     * on a Create page's initial (no-data) load, never on Edit — but the
     * expense fields only ever become visible while EDITING (see
     * configure()), so their ->default(Setting::current()->...) would
     * silently never fire in practice. This field's afterStateUpdated()
     * re-applies that same default (and fetches its exchange rate) the
     * moment status is switched to Completed and no currency is set yet,
     * so the Setting still has an effect on the one page it's ever seen.
     */
    public static function statusField(): ToggleButtons
    {
        return ToggleButtons::make('status')
            ->options(JobStatus::class)
            ->grouped()
            ->default(JobStatus::Pending)
            ->required()
            ->live()
            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                if (JobStatus::resolve($state) !== JobStatus::Completed || filled($get('cost_currency_id'))) {
                    return;
                }

                $currencyId = Setting::current()->repair_cost_currency_id;

                if ($currencyId === null) {
                    return;
                }

                $set('cost_currency_id', $currencyId);
                static::updateExpenseExchangeRate($get, $set, $currencyId);
            });
    }

    /**
     * The "Sent on" date, shared by every place a repair/repaint job is
     * created or edited alongside expenseField() — reactive so changing it
     * re-fetches the expense's historical exchange rate (via
     * updateExpenseExchangeRate(), same as expenseField()'s currency
     * Select), plus a manual "Fetch exchange rate" button for retrying
     * after a transient API failure. `Get`/`Set` read/write `cost`'s
     * sibling fields fine even though this DatePicker isn't inside
     * expenseField()'s own FusedGroup — Get/Set resolve by field path
     * across the whole form, not just the immediate component.
     */
    public static function sentAtField(): DatePicker
    {
        return DatePicker::make('sent_at')
            ->label('Sent on')
            ->default(now())
            ->live()
            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateExpenseExchangeRate($get, $set, $get('cost_currency_id')))
            ->suffixAction(
                Action::make('fetchExchangeRate')
                    ->label('Fetch exchange rate')
                    ->icon(Heroicon::OutlinedArrowPath)
                    // An action embedded in a field runs in its own closure
                    // context — Get/Set must be injected as $schemaGet/
                    // $schemaSet to reach the surrounding form's state.
                    ->action(fn (Get $schemaGet, Set $schemaSet) => static::updateExpenseExchangeRate(
                        $schemaGet,
                        $schemaSet,
                        $schemaGet('cost_currency_id'),
                    )),
            );
    }

    /**
     * Prefills cost_exchange_rate from ExchangeRateFetcher's historical rate
     * for the job's own sent_at date, falling back to the currency's
     * current Currency::exchange_rate when the API can't supply a rate for
     * it — the same pattern as SaleForm's updateExchangeRate() and
     * ShipmentForm's updateCostExchangeRate(). When sent_at isn't part of
     * the schema at all (completeJobAction()'s modal only asks for the
     * expense, not the send date again), fetches today's rate instead —
     * the expense is being entered now, at completion.
     */
    private static function updateExpenseExchangeRate(Get $get, Set $set, mixed $currencyId): void
    {
        if (blank($currencyId)) {
            $set('cost_exchange_rate', null);

            return;
        }

        $currency = Currency::find($currencyId);

        if (! $currency) {
            return;
        }

        $sentAt = $get('sent_at');
        $date = filled($sentAt) ? Carbon::parse($sentAt) : Carbon::now();

        $set('cost_exchange_rate', ExchangeRateFetcher::rate($currency, $date) ?? $currency->exchange_rate);
    }
}
