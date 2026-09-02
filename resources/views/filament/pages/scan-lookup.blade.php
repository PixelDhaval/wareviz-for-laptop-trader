@php
    use App\Enums\JobAssignee;
    use App\Filament\Resources\Laptops\LaptopResource;
    use Filament\Support\Icons\Heroicon;

    $conditionChecklist = $laptop ? [
        'Battery' => $laptop->is_battery_ok,
        'LCD' => $laptop->is_lcd_ok,
        'Bezel' => $laptop->is_bezel_ok,
        'Top cover' => $laptop->is_top_cover_ok,
        'Body' => $laptop->is_body_ok,
        'Back cover' => $laptop->is_back_cover_ok,
        'Keyboard' => $laptop->is_keyboard_ok,
        'Touchpad' => $laptop->is_touchpad_ok,
    ] : [];
@endphp

<x-filament-panels::page>
    <div
        x-data
        x-on:lookup-completed.window="$nextTick(() => $refs.scanInput?.focus())"
    >
        <x-filament::section>
            <form wire:submit="lookup" class="flex flex-col items-stretch gap-3 sm:flex-row">
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

                <x-filament::button type="submit" :icon="Heroicon::OutlinedMagnifyingGlass" size="lg">
                    Look up
                </x-filament::button>
            </form>
        </x-filament::section>

        <div class="mt-6">
            @if ($laptop)
                <x-filament::section :icon="Heroicon::OutlinedComputerDesktop" icon-color="primary">
                    <x-slot name="heading">
                        <div class="flex flex-wrap items-center gap-2">
                            <span>{{ $laptop->brand?->name }} {{ $laptop->laptopModel?->name }}</span>
                            <x-filament::badge :color="$laptop->status->getColor()">
                                {{ $laptop->status->getLabel() }}
                            </x-filament::badge>
                            @if ($laptop->has_issues)
                                <x-filament::badge color="danger" :icon="Heroicon::OutlinedExclamationTriangle">
                                    Needs attention
                                </x-filament::badge>
                            @endif
                        </div>
                    </x-slot>
                    <x-slot name="description">
                        Serial Number <span class="font-mono font-medium">{{ $laptop->asset_code }}</span>
                    </x-slot>
                    <x-slot name="afterHeader">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($laptop->activeRepairJob === null)
                                {{ $this->sendForJobAction }}
                            @else
                                {{ $this->completeJobAction }}
                            @endif
                            <x-filament::icon-button
                                :icon="Heroicon::OutlinedQrCode"
                                label="Print barcode"
                                tag="a"
                                :href="route('laptops.barcode', $laptop)"
                                target="_blank"
                            />
                            <x-filament::icon-button
                                :icon="Heroicon::OutlinedPencilSquare"
                                label="Open in admin"
                                tag="a"
                                :href="LaptopResource::getUrl('edit', ['record' => $laptop])"
                            />
                        </div>
                    </x-slot>

                    <div class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-4">
                        <div>
                            <div class="text-gray-500 dark:text-gray-400">Shipment</div>
                            <div class="font-medium">{{ $laptop->shipment?->code }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500 dark:text-gray-400">Packing list SN</div>
                            <div class="font-medium">{{ $laptop->serial_no ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500 dark:text-gray-400">Processor</div>
                            <div class="font-medium">{{ $laptop->processor?->name }} {{ $laptop->generation?->name }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500 dark:text-gray-400">RAM / Storage</div>
                            <div class="font-medium">{{ $laptop->ram_gb ?? '—' }} GB / {{ $laptop->storage_gb ?? '—' }} GB</div>
                        </div>
                        @if ($laptop->has_builtin_ram)
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Built-in memory</div>
                                <div class="font-medium">
                                    {{ $laptop->builtin_ram_gb ? "{$laptop->builtin_ram_gb} GB RAM" : null }}
                                    {{ $laptop->builtin_storage_gb ? "/ {$laptop->builtin_storage_gb} GB storage" : null }}
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($laptop->activeRepairJob)
                        <div class="mt-6 flex flex-wrap items-center gap-3 rounded-lg border border-info-300 bg-info-50 px-3 py-3 text-sm text-info-700 dark:border-info-800 dark:bg-info-500/10 dark:text-info-400">
                            <x-filament::icon :icon="Heroicon::OutlinedWrenchScrewdriver" class="h-5 w-5 shrink-0" />
                            <div class="flex-1">
                                <span class="font-medium">{{ $laptop->activeRepairJob->type->getLabel() }}</span>
                                in progress &mdash;
                                {{ $laptop->activeRepairJob->assignee === JobAssignee::Agency ? ($laptop->activeRepairJob->agency?->name ?? 'Agency') : 'In-house' }}
                                @if ($laptop->activeRepairJob->sent_at)
                                    since {{ $laptop->activeRepairJob->sent_at->format('M j, Y') }}
                                @endif
                                @if ($laptop->activeRepairJob->cost)
                                    &middot; Expense: {{ number_format((float) $laptop->activeRepairJob->cost, 2) }}
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($laptop->repairJobs->isNotEmpty())
                        <div class="mt-6">
                            <div class="mb-2 text-sm text-gray-500 dark:text-gray-400">Job history</div>
                            <div class="space-y-2">
                                @foreach ($laptop->repairJobs as $job)
                                    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10">
                                        <x-filament::badge :color="$job->status->getColor()">
                                            {{ $job->status->getLabel() }}
                                        </x-filament::badge>
                                        <span class="font-medium">{{ $job->type->getLabel() }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">
                                            {{ $job->assignee === JobAssignee::Agency ? ($job->agency?->name ?? 'Agency') : 'In-house' }}
                                        </span>
                                        <span class="text-gray-500 dark:text-gray-400">{{ $job->sent_at?->format('M j, Y') }}</span>
                                        @if ($job->cost)
                                            <span class="ms-auto text-gray-500 dark:text-gray-400">{{ number_format((float) $job->cost, 2) }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-6">
                        <div class="mb-2 text-sm text-gray-500 dark:text-gray-400">Condition checklist</div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($conditionChecklist as $label => $ok)
                                <div
                                    @class([
                                        'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm',
                                        'border-success-300 bg-success-50 text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400' => $ok,
                                        'border-danger-300 bg-danger-50 text-danger-700 dark:border-danger-800 dark:bg-danger-500/10 dark:text-danger-400' => ! $ok,
                                    ])
                                >
                                    <x-filament::icon
                                        :icon="$ok ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle"
                                        class="h-5 w-5 shrink-0"
                                    />
                                    {{ $label }}
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if ($laptop->issues)
                        <div class="mt-6 flex items-start gap-2 rounded-lg border border-danger-300 bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:border-danger-800 dark:bg-danger-500/10 dark:text-danger-400">
                            <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <div class="font-medium">Issues / remarks</div>
                                <div>{{ $laptop->issues }}</div>
                            </div>
                        </div>
                    @endif
                </x-filament::section>
            @elseif ($notFound)
                <x-filament::section>
                    <div class="flex flex-col items-center gap-2 py-8 text-center">
                        <x-filament::icon :icon="Heroicon::OutlinedFaceFrown" class="h-10 w-10 text-danger-500" />
                        <div class="text-base font-medium">No laptop found</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Nothing matches the serial number <span class="font-mono">{{ $lastQuery }}</span>. Check the code and try again.
                        </div>
                    </div>
                </x-filament::section>
            @else
                <x-filament::section>
                    <div class="flex flex-col items-center gap-2 py-8 text-center">
                        <x-filament::icon :icon="Heroicon::OutlinedQrCode" class="h-10 w-10 text-gray-400" />
                        <div class="text-base font-medium">Ready to scan</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Scan a laptop's barcode sticker, or type its serial number above, to see full product details.
                        </div>
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
