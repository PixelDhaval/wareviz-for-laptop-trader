<?php

namespace App\Filament\Pages;

use App\Enums\CodeDateFormat;
use App\Enums\CodeDateSource;
use App\Enums\CodeSegmentPosition;
use App\Enums\ShipmentCostType;
use App\Models\Setting;
use BackedEnum;
use BokshornIt\FilamentActivityTimeline\Actions\ActivityTimelineAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 99;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getRecord()->attributesToArray());
    }

    protected function getHeaderActions(): array
    {
        return [
            ActivityTimelineAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Sales')
                        ->description('The currency preselected when creating a new sale, per sale type.')
                        ->columns(2)
                        ->schema([
                            static::currencySelect('sale_local_currency_id', 'saleLocalCurrency', 'Local sale currency'),
                            static::currencySelect('sale_export_currency_id', 'saleExportCurrency', 'Export sale currency'),
                        ]),
                    Section::make('Purchase expenses')
                        ->description('The currency preselected on each shipment cost line for import purchases.')
                        ->columns(2)
                        ->schema(array_map(
                            fn (ShipmentCostType $type): Select => static::currencySelect(
                                $type->currencyColumn(),
                                $type->currencyRelationship(),
                                $type->getLabel(),
                            ),
                            ShipmentCostType::cases(),
                        )),
                    Section::make('Local purchases')
                        ->description('The single currency preselected on every cost line of a local-type shipment.')
                        ->schema([
                            static::currencySelect('local_purchase_currency_id', 'localPurchaseCurrency', 'Local purchase currency'),
                        ]),
                    Section::make('Repair expenses')
                        ->description('The currency preselected on a repair/repaint job\'s expense.')
                        ->schema([
                            static::currencySelect('repair_cost_currency_id', 'repairCostCurrency', 'Repair expense currency'),
                        ]),
                    Section::make('Fiscal year')
                        ->description('Used to compute the "FY" date formats below (e.g. 25-26). Leave blank to use the calendar year.')
                        ->schema([
                            Select::make('fiscal_year_start_month')
                                ->label('Fiscal year starts in')
                                ->options(static::monthOptions())
                                ->native(false),
                        ]),
                    Section::make('Laptop asset code')
                        ->description('How new laptops\' asset codes (printed on the barcode label) are generated. Changing this only affects laptops added from now on.')
                        ->columns(3)
                        ->schema([
                            TextInput::make('laptop_code_prefix')
                                ->label('Prefix'),
                            TextInput::make('laptop_code_suffix')
                                ->label('Suffix'),
                            TextInput::make('laptop_code_separator')
                                ->label('Separator')
                                ->helperText('Placed between the prefix, date and sequence number.'),
                            Select::make('laptop_code_date_source')
                                ->label('Date segment source')
                                ->options(CodeDateSource::class)
                                ->native(false),
                            Select::make('laptop_code_date_format')
                                ->label('Date segment format')
                                ->options(CodeDateFormat::class)
                                ->placeholder('No date segment')
                                ->helperText('The sequence number restarts at 1 whenever this segment\'s value changes — pick "year + month" to reset monthly, or "year + month + day" to reset daily.')
                                ->native(false),
                            TextInput::make('laptop_code_sequence_pad')
                                ->label('Sequence digits')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(20)
                                ->required(),
                        ]),
                    Section::make('Sale code')
                        ->description('How new sales\' codes are generated. Changing this only affects sales created from now on.')
                        ->columns(3)
                        ->schema([
                            TextInput::make('sale_code_prefix')
                                ->label('Prefix'),
                            TextInput::make('sale_code_suffix')
                                ->label('Suffix'),
                            TextInput::make('sale_code_separator')
                                ->label('Separator')
                                ->helperText('Placed between the prefix, date and sequence number.'),
                            Select::make('sale_code_date_format')
                                ->label('Date segment format')
                                ->options(CodeDateFormat::class)
                                ->placeholder('No date segment')
                                ->helperText('The sale\'s own date is used. The sequence number restarts at 1 whenever this segment\'s value changes.')
                                ->native(false),
                            Select::make('sale_code_date_position')
                                ->label('Date segment position')
                                ->options(CodeSegmentPosition::class)
                                ->required()
                                ->native(false),
                            TextInput::make('sale_code_sequence_pad')
                                ->label('Sequence digits')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(20)
                                ->required(),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->record($this->getRecord())
            ->statePath('data');
    }

    public function save(): void
    {
        $this->getRecord()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }

    public function getRecord(): Setting
    {
        return Setting::current();
    }

    private static function currencySelect(string $column, string $relationship, string $label): Select
    {
        return Select::make($column)
            ->label($label)
            ->relationship($relationship, 'code')
            ->searchable()
            ->preload();
    }

    /**
     * @return array<int, string>
     */
    private static function monthOptions(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month): array => [$month => Carbon::create(month: $month)->format('F')])
            ->all();
    }
}
