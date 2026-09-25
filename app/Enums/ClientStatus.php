<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ClientStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
