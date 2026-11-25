<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CompetitorType: string
{
    use HasOptions;

    case Direct = 'direct';
    case Indirect = 'indirect';
    case Substitute = 'substitute';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
