<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UpworkJobType: string
{
    use HasOptions;

    case Hourly = 'hourly';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Fixed => 'Fixed price',
        };
    }
}
