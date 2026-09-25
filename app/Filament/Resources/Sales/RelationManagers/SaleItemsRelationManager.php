<?php

namespace App\Filament\Resources\Sales\RelationManagers;

use App\Enums\LaptopStatus;
use App\Filament\Resources\Sales\Schemas\LaptopDetailsPreview;
use App\Models\Currency;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SaleItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'saleItems';

    protected static ?string $title = 'Laptops';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->dense()
            ->components([
                Select::make('laptop_id')
                    ->label('Laptop')
                    ->relationship(
                        name: 'laptop',
                        titleAttribute: 'asset_code',
                        modifyQueryUsing: fn (Builder $query) => $query->where('status', LaptopStatus::InStock),
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                LaptopDetailsPreview::grid(fn (Get $get) => Laptop::find($get('laptop_id')))
                    ->visible(fn (Get $get): bool => filled($get('laptop_id')))
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->prefix(fn (): string => Money::currencySymbol($this->getOwnerRecord()->currency)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('laptop.asset_code')
            ->columns([
                TextColumn::make('laptop.asset_code')
                    ->label('Laptop')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('laptop.brand.name')
                    ->label('Brand / Model')
                    ->formatStateUsing(fn (SaleItem $record) => trim("{$record->laptop?->brand?->name} {$record->laptop?->laptopModel?->name}")),
                TextColumn::make('laptop.processor.name')
                    ->label('Processor')
                    ->formatStateUsing(fn (SaleItem $record) => trim("{$record->laptop?->processor?->name} {$record->laptop?->generation?->name}"))
                    ->toggleable(),
                TextColumn::make('laptop.ram_gb')
                    ->label('RAM')
                    ->suffix(' GB')
                    ->toggleable(),
                TextColumn::make('laptop.storage_gb')
                    ->label('Storage')
                    ->suffix(' GB')
                    ->toggleable(),
                TextInputColumn::make('price')
                    ->type('number')
                    ->rules(['numeric', 'min:0']),
                TextColumn::make('currency.code')
                    ->label('Currency'),
                TextColumn::make('price_in_base_currency')
                    ->label('Price (base)')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (): string => Money::currencyPrefix(Currency::base())),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Add laptop')
                    // SaleItem has no Filament resource of its own, so there
                    // is no Create:SaleItem permission for Filament's default
                    // per-model-policy authorization to find — it would deny
                    // this outright, even for super_admin. Adding a laptop is
                    // really an edit to the sale, so gate it on Sale's own
                    // "update" permission (already generated for
                    // SaleResource) instead of inventing a separate SaleItem
                    // permission nobody would configure.
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var Sale $sale */
                        $sale = $this->getOwnerRecord();

                        return [
                            ...$data,
                            'price_currency_id' => $sale->currency_id,
                            'price_exchange_rate' => $sale->exchange_rate,
                        ];
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Remove')
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Remove selected')
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false),
                ]),
            ]);
    }
}
