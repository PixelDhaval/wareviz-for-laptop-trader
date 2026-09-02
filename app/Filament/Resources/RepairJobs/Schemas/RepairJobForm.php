<?php

namespace App\Filament\Resources\RepairJobs\Schemas;

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;

class RepairJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('laptop_id')
                    ->label('Laptop')
                    ->relationship('laptop', 'asset_code')
                    ->searchable()
                    ->preload()
                    ->required(),
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
                    ->relationship('agency', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn ($get) => JobAssignee::resolve($get('assignee')) === JobAssignee::Agency)
                    ->visible(fn ($get) => JobAssignee::resolve($get('assignee')) === JobAssignee::Agency),
                TextInput::make('cost')
                    ->label('Expense')
                    ->numeric()
                    ->minValue(0),
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
}
