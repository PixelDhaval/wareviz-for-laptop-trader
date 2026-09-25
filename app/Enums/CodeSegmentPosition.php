<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a generated code's date segment sits relative to its sequence
 * number (e.g. SL-2609-0001 vs SL-0001-2609).
 */
enum CodeSegmentPosition: string implements HasLabel
{
    case Before = 'before';
    case After = 'after';

    public function getLabel(): string
    {
        return match ($this) {
            self::Before => 'Before the sequence number',
            self::After => 'After the sequence number',
        };
    }
}
