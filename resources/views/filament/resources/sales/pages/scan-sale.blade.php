@php
    use Filament\Support\Icons\Heroicon;

    $items = $this->scannedItems();
@endphp

<x-filament-panels::page>
    <div
        x-data
        x-on:scan-completed.window="$nextTick(() => $refs.scanInput?.focus())"
    >
        <x-filament::section>
            <form wire:submit="scan" class="flex flex-col items-stretch gap-3 sm:flex-row">
                <div class="flex-1">
                    <x-filament::input.wrapper :prefix-icon="Heroicon::OutlinedQrCode">
                        <x-filament::input
                            type="text"
                            wire:model="code"
                            x-ref="scanInput"
                            placeholder="Scan a barcode or type a serial number, e.g. WV000123"
                            autofocus
                            class="text-base sm:text-lg"
                        />
                    </x-filament::input.wrapper>
                </div>

                <x-filament::button type="submit" :icon="Heroicon::OutlinedQrCode" size="lg">
                    Add
                </x-filament::button>
            </form>

            @if ($message)
                <div
                    @class([
                        'mt-3 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm',
                        'border-danger-300 bg-danger-50 text-danger-700 dark:border-danger-800 dark:bg-danger-500/10 dark:text-danger-400' => $messageIsError,
                        'border-success-300 bg-success-50 text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400' => ! $messageIsError,
                    ])
                >
                    <x-filament::icon
                        :icon="$messageIsError ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle"
                        class="h-5 w-5 shrink-0"
                    />
                    {{ $message }}
                </div>
            @endif
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <span>Laptops in this sale</span>
                    <span class="text-base font-semibold">
                        Total bill: {{ $this->saleCurrencySymbol() }} {{ number_format((float) $this->totalBill(), 2) }}
                    </span>
                </div>
            </x-slot>
            <x-slot name="description">
                {{ $items->count() }} {{ Str::plural('laptop', $items->count()) }} scanned. Use "Save as draft" to come back later, or "Complete sale" once done.
            </x-slot>

            @if ($items->isEmpty())
                <div class="flex flex-col items-center gap-2 py-8 text-center">
                    <x-filament::icon :icon="Heroicon::OutlinedQrCode" class="h-10 w-10 text-gray-400" />
                    <div class="text-base font-medium">Ready to scan</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Scan a laptop's barcode sticker, or type its serial number above. Confirm its price in the popup, and it's added.
                    </div>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($items as $item)
                        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10">
                            <span class="font-mono font-medium">{{ $item->laptop?->asset_code }}</span>
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ $item->laptop?->brand?->name }} {{ $item->laptop?->laptopModel?->name }}
                            </span>
                            <span class="ms-auto font-medium">
                                {{ \App\Support\Money::currencySymbol($item->currency) }} {{ number_format((float) $item->price, 2) }}
                            </span>
                            <div class="flex items-center gap-1">
                                {{ ($this->setPriceAction)(['item' => $item->id]) }}
                                {{ ($this->removeItemAction)(['item' => $item->id]) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
