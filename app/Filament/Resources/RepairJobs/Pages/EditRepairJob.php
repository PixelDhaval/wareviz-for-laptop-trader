<?php

namespace App\Filament\Resources\RepairJobs\Pages;

use App\Filament\Resources\RepairJobs\RepairJobResource;
use BokshornIt\FilamentActivityTimeline\Actions\ActivityTimelineAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRepairJob extends EditRecord
{
    protected static string $resource = RepairJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActivityTimelineAction::make(),
            DeleteAction::make(),
        ];
    }
}
