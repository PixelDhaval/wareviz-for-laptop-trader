<?php

namespace App\Filament\Resources\Laptops\Pages;

use App\Enums\LaptopStatus;
use App\Filament\Resources\Laptops\LaptopResource;
use App\Models\Laptop;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLaptops extends ListRecords
{
    protected static string $resource = LaptopResource::class;

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
            'in_stock' => Tab::make('In Stock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LaptopStatus::InStock))
                ->badge(Laptop::query()->where('status', LaptopStatus::InStock)->count())
                ->badgeColor('success'),
            'reserved' => Tab::make('Reserved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LaptopStatus::Reserved))
                ->badge(Laptop::query()->where('status', LaptopStatus::Reserved)->count())
                ->badgeColor('warning'),
            'sold' => Tab::make('Sold')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LaptopStatus::Sold))
                ->badge(Laptop::query()->where('status', LaptopStatus::Sold)->count())
                ->badgeColor('gray'),
            'defective' => Tab::make('Defective')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LaptopStatus::Defective))
                ->badge(Laptop::query()->where('status', LaptopStatus::Defective)->count())
                ->badgeColor('danger'),
            'needs_attention' => Tab::make('Needs Attention')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('has_issues', true))
                ->badge(Laptop::query()->where('has_issues', true)->count())
                ->badgeColor('danger'),
        ];
    }
}
