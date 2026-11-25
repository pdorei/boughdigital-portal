<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SearchIntent: string
{
    use HasOptions;

    case Informational = 'informational';
    case Commercial = 'commercial';
    case Transactional = 'transactional';
    case Navigational = 'navigational';

    public function label(): string
    {
        return match ($this) {
            self::Informational => 'Informational',
            self::Commercial => 'Commercial',
            self::Transactional => 'Transactional',
            self::Navigational => 'Navigational',
        };
    }
}
