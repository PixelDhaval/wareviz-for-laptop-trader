<?php

namespace App\Filament\Pages;

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Agency;
use App\Models\Laptop;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ScanLookup extends Page
{
    protected string $view = 'filament.pages.scan-lookup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Scan Lookup';

    protected static ?string $title = 'Scan Lookup';

    protected static ?int $navigationSort = 0;

    public ?string $code = null;

    public ?Laptop $laptop = null;

    public bool $notFound = false;

    public ?string $lastQuery = null;

    public function lookup(): void
    {
        $code = trim((string) $this->code);

        $this->laptop = null;
        $this->notFound = false;
        $this->code = null;

        if ($code === '') {
            return;
        }

        $this->lastQuery = $code;

        $this->laptop = $this->findLaptop($code);

        $this->notFound = $this->laptop === null;

        $this->dispatch('lookup-completed');
    }

    public function sendForJobAction(): Action
    {
        return Action::make('sendForJob')
            ->label('Send for repair')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('warning')
            ->visible(fn () => $this->laptop && $this->laptop->activeRepairJob === null)
            ->modalHeading('Send for repair / repaint')
            ->modalSubmitActionLabel('Send')
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
                Select::make('agency_id')
                    ->label('Agency')
                    ->options(fn () => Agency::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(fn ($get) => JobAssignee::resolve($get('assignee')) === JobAssignee::Agency)
                    ->visible(fn ($get) => JobAssignee::resolve($get('assignee')) === JobAssignee::Agency),
                TextInput::make('cost')
                    ->label('Expense')
                    ->numeric()
                    ->minValue(0),
                DatePicker::make('sent_at')
                    ->label('Sent on')
                    ->default(now()),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $this->laptop->repairJobs()->create($data);
                $this->refreshLaptop();
            });
    }

    public function completeJobAction(): Action
    {
        return Action::make('completeJob')
            ->label('Mark job complete')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('This marks the active repair / repaint job as completed and returns the unit to stock.')
            ->visible(fn () => $this->laptop && $this->laptop->activeRepairJob !== null)
            ->action(function (): void {
                $this->laptop->activeRepairJob?->update(['status' => JobStatus::Completed]);
                $this->refreshLaptop();
            });
    }

    protected function findLaptop(string $code): ?Laptop
    {
        return Laptop::query()
            ->with([
                'shipment',
                'brand',
                'laptopModel',
                'processor',
                'generation',
                'activeRepairJob.agency',
                'repairJobs.agency',
            ])
            ->where('asset_code', $code)
            ->first();
    }

    protected function refreshLaptop(): void
    {
        if (! $this->laptop) {
            return;
        }

        $this->laptop = $this->findLaptop($this->laptop->asset_code);
    }
}
