@php
    use App\Enums\JobAssignee;
    use Filament\Support\Icons\Heroicon;

    $activeJob = $record->activeRepairJob;
    $jobs = $record->repairJobs;
@endphp

@if ($activeJob)
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-info-300 bg-info-50 px-3 py-3 text-sm text-info-700 dark:border-info-800 dark:bg-info-500/10 dark:text-info-400">
        <x-filament::icon :icon="Heroicon::OutlinedWrenchScrewdriver" class="h-5 w-5 shrink-0" />
        <div class="flex-1">
            <span class="font-medium">{{ $activeJob->type->getLabel() }}</span>
            in progress &mdash;
            {{ $activeJob->assignee === JobAssignee::Agency ? ($activeJob->agency?->name ?? 'Agency') : 'In-house' }}
            @if ($activeJob->sent_at)
                since {{ $activeJob->sent_at->format('M j, Y') }}
            @endif
            @if ($activeJob->cost)
                &middot; Expense: {{ number_format((float) $activeJob->cost, 2) }}
            @endif
        </div>
    </div>
@endif

@if ($jobs->isNotEmpty())
    <div class="space-y-2">
        @foreach ($jobs as $job)
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
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">No repair or repaint jobs have been logged for this unit yet.</p>
@endif
