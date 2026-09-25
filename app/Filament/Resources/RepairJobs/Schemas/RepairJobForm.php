<?php

namespace App\Filament\Resources\RepairJobs\Schemas;

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Currency;
use App\Support\Money;
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
                static::expenseField(),
                ToggleButtons::make('status')
                    ->options(JobStatus::class)
                    ->grouped()
                    ->default(JobStatus::Pending)
                    ->required()
                    ->live(),
                DatePicker::make('sent_at')
                    ->label('Sent on')
                    ->default(now()),
                DatePicker::make('completed_at')
                    ->label('Completed on')
                    ->visible(fn ($get) => JobStatus::resolve($get('status')) === JobStatus::Completed),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The expense row shared by every place a repair/repaint job is created
     * or edited: an amount, its currency and the exchange rate to the base
     * currency, saved with the job so a later change to a currency's rate
     * never alters a past job's cost. Currency and rate are only required
     * once an amount is entered.
     *
     * `$boundToRecord` selects how the currency is loaded: `true` (the
     * default) uses `->relationship()`, which needs a bound Eloquent record
     * (a resource form or a relation manager). Pass `false` inside a bare
     * `Action::make()->schema()` with no bound record — such as ScanLookup's
     * and LaptopsTable's "Send for repair" modal — where `->relationship()`
     * does not reliably dehydrate; see .ai/rules/filament.md.
     */
    public static function expenseField(bool $boundToRecord = true): FusedGroup
    {
        $hasAmount = fn (Get $get): bool => (float) $get('cost') > 0;

        $currencySelect = Select::make('cost_currency_id')
            ->label('Currency')
            ->placeholder('Currency')
            ->searchable()
            ->live()
            ->required($hasAmount)
            ->afterStateUpdated(function (mixed $state, Set $set): void {
                $set('cost_exchange_rate', filled($state) ? Currency::find($state)?->exchange_rate : null);
            });

        $currencySelect = $boundToRecord
            ? $currencySelect->relationship('currency', 'code')->preload()
            : $currencySelect->options(fn () => Currency::query()->orderBy('code')->pluck('code', 'id'));

        return FusedGroup::make([
            TextInput::make('cost')
                ->label('Expense')
                ->placeholder('Amount')
                ->numeric()
                ->minValue(0)
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
                ->dehydrateStateUsing(fn (mixed $state): mixed => filled($state) ? $state : 1),
        ])
            ->label('Expense')
            ->columns(4)
            ->columnSpanFull();
    }
}
