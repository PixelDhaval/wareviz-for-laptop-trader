<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Enums\LaptopStatus;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Sales\Schemas\LaptopDetailsPreview;
use App\Models\Laptop;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rapid-entry alternative to the Laptops relation manager's "Add laptop"
 * form: scan (or type) a barcode, confirm the laptop and its price in a
 * modal (Enter in the price field submits it), and it's added to the list,
 * ready for the next scan.
 */
class ScanSale extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SaleResource::class;

    protected string $view = 'filament.resources.sales.pages.scan-sale';

    public ?string $code = null;

    public ?string $message = null;

    public bool $messageIsError = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    /**
     * Custom resource pages default to allowing any panel user (see
     * Filament\Pages\Concerns\CanAuthorizeAccess). Scanning changes the sale,
     * so require the same "update" permission Shield generates for editing
     * it, rather than leaving this page open to every role.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record instanceof Sale && (auth()->user()?->can('update', $record) ?? false);
    }

    public function getTitle(): string
    {
        return "Scan laptops — {$this->getRecord()->code}";
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->saveAsDraftAction(),
            $this->completeSaleAction(),
        ];
    }

    /**
     * Leaves is_completed as-is (false, unless it was already completed and
     * is now being re-opened for edits) and returns to the sale — for
     * stepping away mid-scan without signing off on it yet.
     */
    public function saveAsDraftAction(): Action
    {
        return Action::make('saveAsDraft')
            ->label('Save as draft')
            ->color('gray')
            ->icon(Heroicon::OutlinedBookmark)
            ->action(fn () => $this->redirect(SaleResource::getUrl('view', ['record' => $this->getRecord()])));
    }

    /**
     * Marks the sale completed and moves on to its view page — the "done
     * scanning" step. Needs at least one laptop; an empty completed sale is
     * almost certainly a mistake.
     */
    public function completeSaleAction(): Action
    {
        return Action::make('completeSale')
            ->label('Complete sale')
            ->color('success')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->requiresConfirmation()
            ->modalDescription('This marks the sale as completed. You can still edit it afterwards.')
            ->disabled(fn (): bool => $this->getRecord()->saleItems()->doesntExist())
            ->tooltip(fn (): ?string => $this->getRecord()->saleItems()->doesntExist() ? 'Scan at least one laptop first' : null)
            ->action(function (): void {
                $this->getRecord()->update(['is_completed' => true]);
                $this->redirect(SaleResource::getUrl('view', ['record' => $this->getRecord()]));
            });
    }

    /**
     * The running total of every scanned item's price, in the sale's own
     * currency (not the base currency) — see
     * Sale::totalSaleValueInOwnCurrency().
     */
    public function totalBill(): string
    {
        return $this->getRecord()->totalSaleValueInOwnCurrency();
    }

    public function saleCurrencyCode(): ?string
    {
        return $this->getRecord()->currency?->code;
    }

    public function saleCurrencySymbol(): string
    {
        return Money::currencySymbol($this->getRecord()->currency);
    }

    /**
     * Looks up the scanned code and, once it's confirmed sellable, opens the
     * confirm-price modal. The actual add happens in confirmScanAction(),
     * not here — this only validates and hands off.
     */
    public function scan(): void
    {
        $code = trim((string) $this->code);
        $this->code = null;

        if ($code === '') {
            return;
        }

        $laptop = Laptop::query()->where('asset_code', $code)->first();

        if (! ($error = $this->validateScannedLaptop($laptop, $code))) {
            $this->message = null;
            $this->mountAction('confirmScan', arguments: ['laptop' => $laptop->id]);

            return;
        }

        $this->flash($error, isError: true);
    }

    public function confirmScanAction(): Action
    {
        return Action::make('confirmScan')
            ->modalHeading(fn (array $arguments): string => Laptop::find($arguments['laptop'])?->asset_code ?? 'Add laptop')
            ->modalSubmitActionLabel('Add to sale')
            ->modalWidth(Width::ExtraLarge)
            ->schema(function (array $arguments): array {
                $laptop = Laptop::find($arguments['laptop']);

                return [
                    LaptopDetailsPreview::grid(fn (Get $get) => $laptop),
                    TextInput::make('price')
                        ->label('Sale price')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->autofocus()
                        ->prefix(fn (): string => Money::currencySymbol($this->getRecord()->currency)),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $laptop = Laptop::find($arguments['laptop']);

                // Re-validate: time has passed since the modal opened, so
                // another tab could have sold this same laptop in the
                // meantime.
                if ($error = $this->validateScannedLaptop($laptop, (string) $arguments['laptop'])) {
                    Notification::make()->title($error)->danger()->send();

                    return;
                }

                $this->getRecord()->saleItems()->create([
                    'laptop_id' => $laptop->id,
                    'price' => $data['price'],
                    'price_currency_id' => $this->getRecord()->currency_id,
                    'price_exchange_rate' => $this->getRecord()->exchange_rate,
                ]);

                $this->flash("{$laptop->asset_code} added to the sale.");
            });
    }

    /**
     * @return Collection<int, SaleItem>
     */
    public function scannedItems(): Collection
    {
        return $this->getRecord()->saleItems()
            ->with(['laptop.brand', 'laptop.laptopModel', 'currency'])
            ->latest()
            ->get();
    }

    public function setPriceAction(): Action
    {
        return Action::make('setPrice')
            ->label('Set price')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->fillForm(fn (array $arguments): array => [
                'price' => SaleItem::find($arguments['item'])?->price,
            ])
            ->schema([
                TextInput::make('price')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->prefix(fn (): string => Money::currencySymbol($this->getRecord()->currency)),
            ])
            ->action(function (array $data, array $arguments): void {
                SaleItem::query()
                    ->where('sale_id', $this->getRecord()->id)
                    ->whereKey($arguments['item'])
                    ->first()
                    ?->update(['price' => $data['price']]);
            });
    }

    public function removeItemAction(): Action
    {
        return Action::make('removeItem')
            ->label('Remove')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This removes the laptop from the sale and returns it to stock.')
            ->action(function (array $arguments): void {
                SaleItem::query()
                    ->where('sale_id', $this->getRecord()->id)
                    ->whereKey($arguments['item'])
                    ->first()
                    ?->delete();
            });
    }

    /**
     * Null when the laptop can be added; otherwise the user-facing reason it
     * can't. `$identifier` is shown in the "not found" message and is either
     * the raw scanned code or, on the re-check inside confirmScanAction(),
     * the laptop's own id (the laptop is already known to exist by then).
     */
    private function validateScannedLaptop(?Laptop $laptop, string $identifier): ?string
    {
        if (! $laptop) {
            return "No laptop found for \"{$identifier}\".";
        }

        if ($this->getRecord()->saleItems()->where('laptop_id', $laptop->id)->exists()) {
            return "{$laptop->asset_code} is already in this sale.";
        }

        if ($laptop->status !== LaptopStatus::InStock) {
            return "{$laptop->asset_code} is {$laptop->status->getLabel()}, not available to sell.";
        }

        return null;
    }

    private function flash(string $message, bool $isError = false): void
    {
        $this->message = $message;
        $this->messageIsError = $isError;
        $this->dispatch('scan-completed');
    }
}
