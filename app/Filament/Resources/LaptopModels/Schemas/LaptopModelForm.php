<?php

namespace App\Filament\Resources\LaptopModels\Schemas;

use App\Filament\Resources\Brands\Schemas\BrandForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LaptopModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('brand_id')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm(fn (Schema $schema) => BrandForm::configure($schema)),
                TextInput::make('name')
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, $get) => $rule->where('brand_id', $get('brand_id')),
                    ),
            ]);
    }
}
