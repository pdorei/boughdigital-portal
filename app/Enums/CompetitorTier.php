<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CompetitorTier: string
{
    use HasOptions;

    case Primary = 'primary';
    case Secondary = 'secondary';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
