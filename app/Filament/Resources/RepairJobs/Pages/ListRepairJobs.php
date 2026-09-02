<?php

namespace App\Filament\Resources\RepairJobs\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\RepairJobs\RepairJobResource;
use App\Models\RepairJob;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRepairJobs extends ListRecords
{
    protected static string $resource = RepairJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', JobStatus::Pending))
                ->badge(RepairJob::query()->where('status', JobStatus::Pending)->count())
                ->badgeColor('warning'),
            'in_progress' => Tab::make('In Progress')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', JobStatus::InProgress))
                ->badge(RepairJob::query()->where('status', JobStatus::InProgress)->count())
                ->badgeColor('info'),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', JobStatus::Completed))
                ->badge(RepairJob::query()->where('status', JobStatus::Completed)->count())
                ->badgeColor('success'),
            'cancelled' => Tab::make('Cancelled')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', JobStatus::Cancelled))
                ->badge(RepairJob::query()->where('status', JobStatus::Cancelled)->count())
                ->badgeColor('gray'),
        ];
    }
}
