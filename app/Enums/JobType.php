<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JobType: string implements HasLabel
{
    case Repair = 'repair';
    case Repaint = 'repaint';

    public function getLabel(): string
    {
        return match ($this) {
            self::Repair => 'Repair',
            self::Repaint => 'Repaint',
        };
    }
}
