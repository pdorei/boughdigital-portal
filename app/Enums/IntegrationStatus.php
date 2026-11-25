<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum IntegrationStatus: string
{
    use HasOptions;

    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Error = 'error';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
